<?php

namespace App\Services;

use App\Models\Channel;
use App\Models\VodPlaylistItem;
use Illuminate\Support\Facades\Storage;

class FFmpegCommandBuilder
{
    public function buildVodWithOverlayCommand(Channel $channel): string
    {
        $overlay = $channel->overlaySetting;
        $filterComplex = [];
        $inputs = [];

        $mode = $channel->playlist_mode ?? 'sequential';

        $query = $channel->vodPlaylistItems()->where('status', 'active');

        if ($mode === 'scheduled') {
            $playlistItems = $query->orderBy('scheduled_at')->orderBy('order')->get();
        } else {
            $playlistItems = $query->orderBy('order')->get();
        }

        if ($playlistItems->isEmpty()) {
            return $this->buildBlackScreenCommand($channel);
        }

        $inputIndex = 0;
        $concatParts = [];
        $useXfade = false;

        foreach ($playlistItems as $item) {
            $inputPath = $this->resolvePlaylistItemPath($item);
            if (!$inputPath) continue;

            $loopCount = max(1, (int)($item->loop_count ?? 1));

            for ($l = 0; $l < $loopCount; $l++) {
                $inputs[] = $inputPath;
                $concatParts[] = "[{$inputIndex}:v]";
                $inputIndex++;
            }

            if ($item->transition !== 'cut' && $item->transition !== null) {
                $useXfade = true;
            }
        }

        if (empty($concatParts)) {
            return $this->buildBlackScreenCommand($channel);
        }

        $totalInputs = count($concatParts);
        $currentVideo = null;

        if ($totalInputs === 1) {
            $currentVideo = '0:v';
        } elseif ($useXfade && $totalInputs > 1) {
            $filterComplex[] = $this->buildXfadeChain($totalInputs, $concatParts);
            $currentVideo = 'xfade_out';
        } else {
            $concatFilter = implode('', $concatParts) . "concat=n={$totalInputs}:v=1:a=0";
            $filterComplex[] = $concatFilter;
            $currentVideo = 'concat_out';
        }

        if ($overlay && $overlay->enabled) {
            $filterComplex[] = $this->buildOverlayFilters($overlay, $currentVideo, $inputs, $inputIndex);
            $currentVideo = 'overlay_out';
        }

        $filterComplexStr = implode(';', array_filter($filterComplex));
        $host = config('flussonic.host', '127.0.0.1');
        $rtmpPort = config('flussonic.rtmp_port', 1935);

        $cmd = 'ffmpeg -re';
        if ($channel->playlist_loop) {
            $cmd .= ' -stream_loop -1';
        }
        $cmd .= ' ';
        foreach ($inputs as $i => $input) {
            $cmd .= "-i \"{$input}\" ";
        }

        if ($filterComplexStr) {
            $cmd .= "-filter_complex \"{$filterComplexStr}\" ";
        }

        $cmd .= "-map \"[{$currentVideo}]\" ";

        $cmd .= "-c:v libx264 -preset veryfast -b:v 2000k -maxrate 2500k -bufsize 4000k ";
        $cmd .= "-c:a aac -b:a 128k -ar 44100 ";
        $cmd .= "-g 60 -keyint_min 60 -sc_threshold 0 ";
        $cmd .= "-f flv \"rtmp://{$host}:{$rtmpPort}/static/{$channel->stream_key}\"";

        return $cmd;
    }

    public function buildBlackScreenCommand(Channel $channel): string
    {
        $host = config('flussonic.host', '127.0.0.1');
        $rtmpPort = config('flussonic.rtmp_port', 1935);
        $text = $channel->name ?? 'Stream Offline';

        return "ffmpeg -re -f lavfi -i color=c=black:s=1920x1080:d=1 " .
            "-vf \"drawtext=fontfile={$this->getFontPath()}:text='{$text}':fontcolor=white:fontsize=48:x=(w-tw)/2:y=(h-th)/2," .
            "loop=-1:size=1:start=0\" " .
            "-c:v libx264 -preset ultrafast -b:v 500k " .
            "-c:a anullsrc -b:a 32k -ar 44100 -ac 2 " .
            "-f flv \"rtmp://{$host}:{$rtmpPort}/static/{$channel->stream_key}\"";
    }

    private function buildXfadeChain(int $count, array $labels): string
    {
        $parts = [];
        $xfadeDuration = 0.5;
        $lastOut = '';

        foreach ($labels as $i => $label) {
            if ($i === 0) {
                $parts[] = "{$label}null[v0]";
                $lastOut = 'v0';
            } else {
                $nextOut = "v{$i}";
                $parts[] = "[{$lastOut}]{$label}xfade=transition=fade:duration={$xfadeDuration}:offset=0[{$nextOut}]";
                $lastOut = $nextOut;
            }
        }

        $parts[] = "[{$lastOut}]null[xfade_out]";
        return implode(';', $parts);
    }

    private function buildOverlayFilters($overlay, string $inputVideo, array &$inputs, int &$inputIndex): string
    {
        $filters = [];
        $currentVideo = $inputVideo;

        if ($overlay->logo_path && Storage::disk('public')->exists($overlay->logo_path)) {
            $logoPath = Storage::disk('public')->path($overlay->logo_path);
            $inputs[] = $logoPath;
            $logoLabel = "[{$inputIndex}:v]";
            $filters[] = "{$logoLabel}[{$currentVideo}]overlay=" . $this->getOverlayPosition($overlay->logo_position) . ":enable='between(t,0,999999)'";
            $currentVideo = "logo_overlay";
            $inputIndex++;
        }

        if ($overlay->ticker_text) {
            $fontFile = $this->getFontPath();
            $escapedText = str_replace("'", "\\'", $overlay->ticker_text);
            $speed = $overlay->ticker_speed;
            $bgColor = $overlay->ticker_background_color;
            $fontColor = $overlay->ticker_font_color;
            $fontSize = $overlay->ticker_font_size;

            $tickerY = 'h-th-10';
            $drawText = ":fontfile='{$fontFile}':fontsize={$fontSize}:fontcolor={$fontColor}:" .
                "box=1:boxcolor={$bgColor}:boxborderw=5:" .
                "x='w-mod(t*{$speed},w+tw)':y={$tickerY}:text='{$escapedText}'";

            $filters[] = "[{$currentVideo}]drawtext={$drawText}[overlay_out]";
            return implode(';', $filters);
        }

        if ($overlay->show_clock) {
            $filters[] = "[{$currentVideo}]drawtext=" .
                ":fontfile='{$this->getFontPath()}':fontsize=24:fontcolor=white:" .
                "x='w-tw-10':y=10:text='%{localtime\\:%Y-%m-%d %H\\\\:%M\\\\:%S}'[overlay_out]";
            return implode(';', $filters);
        }

        $filters[] = "[{$currentVideo}]null[overlay_out]";
        return implode(';', $filters);
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
            return $item->file_path_or_url;
        }

        return null;
    }

    private function getOverlayPosition(string $position): string
    {
        return match ($position) {
            'top-left' => '10:10',
            'top-right' => 'W-w-10:10',
            'bottom-left' => '10:H-h-10',
            'bottom-right' => 'W-w-10:H-h-10',
            'center' => '(W-w)/2:(H-h)/2',
            default => '10:10',
        };
    }

    private function getFontPath(): string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return 'C\:/Windows/Fonts/arial.ttf';
        }
        return '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';
    }
}
