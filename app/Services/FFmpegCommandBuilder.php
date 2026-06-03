<?php

namespace App\Services;

use App\Models\Channel;
use App\Models\VodPlaylistItem;
use Illuminate\Support\Facades\Storage;

class FFmpegCommandBuilder
{
    public function __construct(
        private ?YouTubeService $youtubeService = null,
    ) {
        $this->youtubeService ??= app(YouTubeService::class);
    }
    public function buildVodWithOverlayCommand(Channel $channel): string
    {
        $overlay  = $channel->overlaySetting;
        $mode     = $channel->playlist_mode ?? 'sequential';
        $query    = $channel->vodPlaylistItems()->where('status', 'active');

        $items = $mode === 'scheduled'
            ? $query->orderBy('scheduled_at')->orderBy('order')->get()
            : $query->orderBy('order')->get();

        if ($items->isEmpty()) {
            return $this->buildBlackScreenCommand($channel);
        }

        $inputs       = [];
        $concatParts  = [];
        $inputIndex   = 0;
        $useXfade     = false;

        foreach ($items as $item) {
            $path = $this->resolvePlaylistItemPath($item);
            if (!$path) continue;

            $loops = max(1, (int)($item->loop_count ?? 1));

            // YouTube returns video|audio as two separate stream URLs
            $isYtSplit = str_contains($path, '|');

            for ($l = 0; $l < $loops; $l++) {
                if ($isYtSplit) {
                    [$vidUrl, $audUrl] = explode('|', $path, 2);
                    $inputs[]      = $vidUrl;
                    $inputs[]      = $audUrl;
                    // video from first input, audio from second input of this pair
                    $concatParts[] = "[{$inputIndex}:v][" . ($inputIndex + 1) . ":a]";
                    $inputIndex   += 2;
                } else {
                    $inputs[]      = $path;
                    $concatParts[] = "[{$inputIndex}:v][{$inputIndex}:a]";
                    $inputIndex++;
                }
            }

            if (!in_array($item->transition, ['cut', null], true)) {
                $useXfade = true;
            }
        }

        if (empty($inputs)) {
            return $this->buildBlackScreenCommand($channel);
        }

        $filterComplex = [];
        $currentVideo  = $concatParts[0] === '[0:v][0:a]' ? '0:v' : '0:v';
        $currentAudio  = count($inputs) > count($concatParts) ? '1:a' : '0:a'; // YT split: audio on input 1
        $total         = count($concatParts);

        if ($total > 1) {
            if ($useXfade) {
                [$currentVideo, $currentAudio, $xfadeFilter] = $this->buildXfadeChain($total);
                $filterComplex[] = $xfadeFilter;
            } else {
                $concatIn = implode('', $concatParts);
                $filterComplex[] = "{$concatIn}concat=n={$total}:v=1:a=1[vconcat][aconcat]";
                $currentVideo = 'vconcat';
                $currentAudio = 'aconcat';
            }
        }

        if ($overlay && $overlay->enabled) {
            [$currentVideo, $currentAudio, $overlayFilters] = $this->buildAllOverlayFilters(
                $overlay, $currentVideo, $currentAudio, $inputs, $inputIndex
            );
            foreach ($overlayFilters as $f) {
                $filterComplex[] = $f;
            }
        }

        $filterStr = implode(';', array_filter($filterComplex));
        $outputUrl = $this->buildOutputUrl($channel);

        $cmd = 'ffmpeg -re';
        if ($channel->playlist_loop) {
            $cmd .= ' -stream_loop -1';
        }
        foreach ($inputs as $input) {
            $cmd .= " -i \"{$input}\"";
        }

        if ($filterStr) {
            $cmd .= " -filter_complex \"{$filterStr}\"";
            $cmd .= " -map \"[{$currentVideo}]\" -map \"[{$currentAudio}]\"";
        } else {
            // No filter - map video and audio directly
            $isYtSplit = count($inputs) > count($concatParts);
            $cmd .= " -map 0:v -map " . ($isYtSplit ? '1:a' : '0:a');
        }

        $cmd .= " -c:v libx264 -preset veryfast -b:v 2000k -maxrate 2500k -bufsize 4000k";
        $cmd .= " -c:a aac -b:a 128k -ar 44100 -ac 2";
        $cmd .= " -g 60 -keyint_min 60 -sc_threshold 0";
        $cmd .= " {$this->buildOutputFormat($channel)} \"{$outputUrl}\"";

        return $cmd;
    }

    public function buildBlackScreenCommand(Channel $channel): string
    {
        $text      = $this->escapeFFmpegText($channel->name ?? 'Stream Offline');
        $appName   = $this->escapeFFmpegText(config('app.name', 'HybridStream'));
        $outputUrl = $this->buildOutputUrl($channel);
        $font      = $this->getFontPath();

        return "ffmpeg -re -f lavfi -i color=c=black:s=1920x1080:r=25 -f lavfi -i anullsrc=r=44100:cl=stereo" .
            " -filter_complex" .
            " \"drawtext=fontfile='{$font}':text='{$appName}':fontcolor=white:fontsize=36:x=(w-tw)/2:y=(h-th)/2-60:alpha=0.6," .
            "drawtext=fontfile='{$font}':text='{$text}':fontcolor=white:fontsize=52:x=(w-tw)/2:y=(h-th)/2," .
            "drawtext=fontfile='{$font}':text='Stream Temporarily Offline':fontcolor=0xFFAAAA:fontsize=28:x=(w-tw)/2:y=(h-th)/2+70\"" .
            " -c:v libx264 -preset ultrafast -b:v 500k -tune zerolatency" .
            " -c:a aac -b:a 32k -ar 44100 -ac 2" .
            " {$this->buildOutputFormat($channel)} \"{$outputUrl}\"";
    }

    private function buildOutputUrl(Channel $channel): string
    {
        $host    = config('flussonic.host', '127.0.0.1');
        $key     = $channel->stream_key;
        $protocol = $channel->ingest_protocol;
        $port    = $channel->ingest_port;

        return match ($protocol) {
            'srt'    => "srt://{$host}:{$port}?streamid=static/{$key}&pkt_size=1316",
            'rtsp'   => "rtsp://{$host}:{$port}/static/{$key}",
            'mpegts' => "udp://{$host}:{$port}",
            default  => "rtmp://{$host}:1935/static/{$key}",
        };
    }

    private function buildOutputFormat(Channel $channel): string
    {
        return match ($channel->ingest_protocol) {
            'srt'    => '-f mpegts',
            'rtsp'   => '-f rtsp -rtsp_transport tcp',
            'mpegts' => '-f mpegts',
            default  => '-f flv',
        };
    }

    private function buildAllOverlayFilters($overlay, string $currentVideo, string $currentAudio, array &$inputs, int &$inputIndex): array
    {
        $filters = [];
        $font    = $this->getFontPath();

        // Logo
        if ($overlay->logo_path && Storage::disk('public')->exists($overlay->logo_path)) {
            $logoPath   = Storage::disk('public')->path($overlay->logo_path);
            $pos        = $this->getOverlayPosition($overlay->logo_position ?? 'top-left');
            $w          = (int)($overlay->logo_width ?? 150);
            $inputs[]   = $logoPath;
            $logoIdx    = $inputIndex++;
            $nextVideo  = "after_logo";
            $filters[]  = "[{$logoIdx}:v]scale={$w}:-1[logo_scaled];[{$currentVideo}][logo_scaled]overlay={$pos}[{$nextVideo}]";
            $currentVideo = $nextVideo;
        }

        // Ticker (scrolling text top or bottom)
        if ($overlay->ticker_text) {
            $text     = $this->escapeFFmpegText($overlay->ticker_text);
            $speed    = (int)($overlay->ticker_speed ?? 50);
            $bgColor  = $this->hexToFFmpegColor($overlay->ticker_background_color ?? '#00000080');
            $fgColor  = $this->hexToFFmpegColor($overlay->ticker_font_color ?? '#FFFFFF');
            $fontSize = (int)($overlay->ticker_font_size ?? 24);
            $nextVideo = "after_ticker";
            $filters[] = "[{$currentVideo}]drawtext=fontfile='{$font}':text='{$text}':fontsize={$fontSize}:fontcolor={$fgColor}:box=1:boxcolor={$bgColor}:boxborderw=5:x='w-mod(t*{$speed}\\,w+tw)':y=h-th-10[{$nextVideo}]";
            $currentVideo = $nextVideo;
        }

        // Crawl (news crawl at very bottom, separate from ticker)
        if ($overlay->show_crawl && $overlay->crawl_text) {
            $text     = $this->escapeFFmpegText($overlay->crawl_text);
            $speed    = (int)($overlay->crawl_speed ?? 80);
            $bgColor  = $this->hexToFFmpegColor($overlay->crawl_bg_color ?? '#000000CC');
            $fgColor  = $this->hexToFFmpegColor($overlay->crawl_text_color ?? '#FFFF00');
            $fontSize = (int)($overlay->crawl_font_size ?? 28);
            $nextVideo = "after_crawl";
            $filters[] = "[{$currentVideo}]drawtext=fontfile='{$font}':text='{$text}':fontsize={$fontSize}:fontcolor={$fgColor}:box=1:boxcolor={$bgColor}:boxborderw=8:x='w-mod(t*{$speed}\\,w+tw)':y=h-th-2[{$nextVideo}]";
            $currentVideo = $nextVideo;
        }

        // Lower third
        if ($overlay->show_lower_third && $overlay->lower_third_title) {
            $title    = $this->escapeFFmpegText($overlay->lower_third_title);
            $subtitle = $overlay->lower_third_subtitle ? $this->escapeFFmpegText($overlay->lower_third_subtitle) : null;
            $bgColor  = $this->hexToFFmpegColor($overlay->lower_third_bg_color ?? '#1a1a1aCC');
            $fgColor  = $this->hexToFFmpegColor($overlay->lower_third_text_color ?? '#FFFFFF');
            $fontSize = (int)($overlay->lower_third_font_size ?? 32);
            $duration = (int)($overlay->lower_third_duration ?? 5);
            $pos      = $this->getLowerThirdPosition($overlay->lower_third_position ?? 'bottom-left');
            $nextVideo = "after_lt";

            $titleFilter = "drawtext=fontfile='{$font}':text='{$title}':fontsize={$fontSize}:fontcolor={$fgColor}:box=1:boxcolor={$bgColor}:boxborderw=10:{$pos['title']}:enable='lt(mod(t\\,30)\\,{$duration})'";

            if ($subtitle) {
                $subFontSize = max(16, $fontSize - 8);
                $subtitleFilter = "drawtext=fontfile='{$font}':text='{$subtitle}':fontsize={$subFontSize}:fontcolor={$fgColor}:box=1:boxcolor={$bgColor}:boxborderw=6:{$pos['subtitle']}:enable='lt(mod(t\\,30)\\,{$duration})'";
                $filters[] = "[{$currentVideo}]{$titleFilter},{$subtitleFilter}[{$nextVideo}]";
            } else {
                $filters[] = "[{$currentVideo}]{$titleFilter}[{$nextVideo}]";
            }
            $currentVideo = $nextVideo;
        }

        // Clock
        if ($overlay->show_clock) {
            $clockPos   = $this->getClockPosition($overlay->clock_position ?? 'top-right');
            $nextVideo  = "after_clock";
            $filters[]  = "[{$currentVideo}]drawtext=fontfile='{$font}':text='%{localtime\\:%H\\:%M\\:%S}':fontsize=24:fontcolor=white:box=1:boxcolor=black@0.5:boxborderw=5:{$clockPos}[{$nextVideo}]";
            $currentVideo = $nextVideo;
        }

        // Pass audio through filter graph so it can be mapped by label
        $filters[] = "[{$currentAudio}]anull[aout]";

        return [$currentVideo, 'aout', $filters];
    }

    private function buildXfadeChain(int $total): array
    {
        $parts    = [];
        $duration = 0.5;
        $lastV    = '0:v';
        $lastA    = '0:a';

        for ($i = 1; $i < $total; $i++) {
            $outV    = "xv{$i}";
            $outA    = "xa{$i}";
            $parts[] = "[{$lastV}][{$i}:v]xfade=transition=fade:duration={$duration}:offset=0[{$outV}]";
            $parts[] = "[{$lastA}][{$i}:a]acrossfade=d={$duration}[{$outA}]";
            $lastV   = $outV;
            $lastA   = $outA;
        }

        return [$lastV, $lastA, implode(';', $parts)];
    }

    private function resolvePlaylistItemPath(VodPlaylistItem $item): ?string
    {
        if ($item->type === 'upload') {
            $path = $item->file_path_or_url;
            return Storage::disk('public')->exists($path)
                ? Storage::disk('public')->path($path)
                : null;
        }

        if ($item->type === 'youtube') {
            return $this->resolveYoutubeUrl($item->file_path_or_url);
        }

        return null;
    }

    private function resolveYoutubeUrl(string $url): ?string
    {
        $resolved = $this->youtubeService->resolveStreamUrl($url);
        
        // Always return the resolved URL, even if it's the original URL
        // Flussonic can handle YouTube URLs directly in many cases
        return $resolved;
    }

    private function getOverlayPosition(string $position): string
    {
        return match ($position) {
            'top-right'    => 'W-w-10:10',
            'bottom-left'  => '10:H-h-10',
            'bottom-right' => 'W-w-10:H-h-10',
            'center'       => '(W-w)/2:(H-h)/2',
            default        => '10:10',
        };
    }

    private function getLowerThirdPosition(string $position): array
    {
        return match ($position) {
            'bottom-right' => ['title' => 'x=w-tw-20:y=h-th-60', 'subtitle' => 'x=w-tw-20:y=h-th-30'],
            'center'       => ['title' => 'x=(w-tw)/2:y=h-th-60', 'subtitle' => 'x=(w-tw)/2:y=h-th-30'],
            default        => ['title' => 'x=20:y=h-th-60', 'subtitle' => 'x=20:y=h-th-30'],
        };
    }

    private function getClockPosition(string $position): string
    {
        return match ($position) {
            'top-left'     => 'x=10:y=10',
            'bottom-right' => 'x=w-tw-10:y=h-th-10',
            'bottom-left'  => 'x=10:y=h-th-10',
            default        => 'x=w-tw-10:y=10',
        };
    }

    private function hexToFFmpegColor(string $hex): string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 8) {
            // RRGGBBAA -> 0xRRGGBB@alpha
            $r     = hexdec(substr($hex, 0, 2));
            $g     = hexdec(substr($hex, 2, 2));
            $b     = hexdec(substr($hex, 4, 2));
            $alpha = round(hexdec(substr($hex, 6, 2)) / 255, 2);
            return "0x" . strtoupper(substr($hex, 0, 6)) . "@{$alpha}";
        }
        return "0x{$hex}";
    }

    private function escapeFFmpegText(string $text): string
    {
        return str_replace(["'", ':', '\\'], ["\\'", '\\:', '\\\\'], $text);
    }

    private function getFontPath(): string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return 'C\\:/Windows/Fonts/arial.ttf';
        }
        return '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';
    }
}
