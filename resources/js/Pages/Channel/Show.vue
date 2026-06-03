<script setup>
import { ref, computed } from 'vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
  channel: Object,
  overlay_settings: Object,
  overlay_preview: Object,
  vod_items: Array,
  playlist_stats: Object,
  playlist_timeline: Array,
  health_logs: Array,
})

const activeTab = ref('overview')

const tabs = [
  { key: 'overview', label: 'Overview' },
  { key: 'ingest', label: 'Ingest' },
  { key: 'egress', label: 'Egress' },
  { key: 'vod', label: 'VOD Playlist' },
  { key: 'overlay', label: 'Overlay' },
  { key: 'logs', label: 'Logs' },
]

const uploadForm = useForm({ file: null, title: '' })
const youtubeForm = useForm({ url: '', title: '' })

// Playlist settings
const playlistSettings = useForm({
  playlist_mode: props.channel.playlist_mode || 'sequential',
  playlist_loop: props.channel.playlist_loop ?? true,
  playlist_fill_action: props.channel.playlist_fill_action || 'black',
})

// Editing a single playlist item
const editingItemId = ref(null)
const editForm = useForm({
  title: '', scheduled_at: '', duration_override: null,
  loop_count: 1, transition: 'cut', status: 'active',
})

function startEditItem(item) {
  editingItemId.value = item.id
  editForm.title = item.title
  editForm.scheduled_at = item.scheduled_at?.slice(0, 16) || ''
  editForm.duration_override = item.duration_override
  editForm.loop_count = item.loop_count || 1
  editForm.transition = item.transition || 'cut'
  editForm.status = item.status || 'active'
}

function cancelEditItem() {
  editingItemId.value = null
}

function saveEditItem(itemId) {
  editForm.put(route('channel.vod.update', { channel: props.channel.id, vodItemId: itemId }), {
    onSuccess: () => { editingItemId.value = null }
  })
}

function handleUpload() {
  uploadForm.post(route('channel.vod.upload', { channel: props.channel.id }), {
    onSuccess: () => uploadForm.reset()
  })
}

function handleYoutubeAdd() {
  youtubeForm.post(route('channel.vod.youtube', { channel: props.channel.id }), {
    onSuccess: () => youtubeForm.reset()
  })
}

function deleteVodItem(itemId) {
  if (confirm('Remove this item from the playlist?')) {
    router.delete(route('channel.vod.delete', { channel: props.channel.id, vodItemId: itemId }))
  }
}

function toggleItemStatus(item) {
  router.put(route('channel.vod.update', { channel: props.channel.id, vodItemId: item.id }), {
    status: item.status === 'active' ? 'paused' : 'active',
  }, { preserveScroll: true })
}

function savePlaylistSettings() {
  playlistSettings.put(route('channel.playlist.settings', { channel: props.channel.id }))
}

// Channel settings
const channelForm = useForm({
  name: props.channel.name,
  ingest_protocol: props.channel.ingest_protocol,
  output_protocols_json: props.channel.output_protocols_json || [],
})

const overlayForm = useForm({
  logo_position: props.overlay_settings?.logo_position || 'top-left',
  logo_width: props.overlay_settings?.logo_width || 150,
  ticker_text: props.overlay_settings?.ticker_text || '',
  ticker_speed: props.overlay_settings?.ticker_speed || 50,
  ticker_direction: props.overlay_settings?.ticker_direction || 'left',
  ticker_background_color: props.overlay_settings?.ticker_background_color || '#00000080',
  ticker_font_color: props.overlay_settings?.ticker_font_color || '#FFFFFF',
  ticker_font_size: props.overlay_settings?.ticker_font_size || 24,
  show_clock: props.overlay_settings?.show_clock || false,
  clock_position: props.overlay_settings?.clock_position || 'top-right',
  enabled: props.overlay_settings?.enabled ?? true,
  lower_third_title: props.overlay_settings?.lower_third_title || '',
  lower_third_subtitle: props.overlay_settings?.lower_third_subtitle || '',
  lower_third_position: props.overlay_settings?.lower_third_position || 'bottom-left',
  lower_third_bg_color: props.overlay_settings?.lower_third_bg_color || '#1a1a1aCC',
  lower_third_text_color: props.overlay_settings?.lower_third_text_color || '#FFFFFF',
  lower_third_font_size: props.overlay_settings?.lower_third_font_size || 32,
  lower_third_duration: props.overlay_settings?.lower_third_duration || 5,
  show_lower_third: props.overlay_settings?.show_lower_third || false,
  crawl_text: props.overlay_settings?.crawl_text || '',
  crawl_speed: props.overlay_settings?.crawl_speed || 80,
  crawl_bg_color: props.overlay_settings?.crawl_bg_color || '#000000CC',
  crawl_text_color: props.overlay_settings?.crawl_text_color || '#FFFF00',
  crawl_font_size: props.overlay_settings?.crawl_font_size || 28,
  show_crawl: props.overlay_settings?.show_crawl || false,
})

const availableProtocols = ['rtmp', 'srt', 'rtsp', 'hls']
const ingestProtocols = ['srt', 'rtmp', 'rtsp', 'mpegts']

function toggleOutputProtocol(protocol) {
  const idx = channelForm.output_protocols_json.indexOf(protocol)
  if (idx > -1) channelForm.output_protocols_json.splice(idx, 1)
  else channelForm.output_protocols_json.push(protocol)
}

const logoForm = useForm({ logo: null })

function handleLogoUpload() {
  logoForm.post(route('channel.overlay.logo', { channel: props.channel.id }), {
    onSuccess: () => logoForm.reset()
  })
}

const ingestUrl = computed(() => {
  const host = window.location.hostname
  const p = props.channel.ingest_protocol
  const port = props.channel.ingest_port
  const key = props.channel.stream_key
  switch (p) {
    case 'rtmp': return `rtmp://${host}:${port}/static/${key}`
    case 'srt': return `srt://${host}:${port}?streamid=static/${key}`
    case 'rtsp': return `rtsp://${host}:${port}/static/${key}`
    case 'mpegts': return `udp://${host}:${port}`
    default: return `rtmp://${host}:${port}/static/${key}`
  }
})

const outputUrls = computed(() => {
  const host = window.location.hostname
  const key = props.channel.stream_key
  return {
    hls: `http://${host}/static/${key}/index.m3u8`,
    rtmp: `rtmp://${host}:1935/static/${key}`,
    dash: `http://${host}/static/${key}/manifest.mpd`,
    screenshot: `http://${host}/static/${key}/screenshot.jpg`,
  }
})

const formatDuration = (sec) => {
  if (!sec) return '0s'
  const h = Math.floor(sec / 3600)
  const m = Math.floor((sec % 3600) / 60)
  const s = Math.floor(sec % 60)
  if (h > 0) return `${h}h ${m}m ${s}s`
  if (m > 0) return `${m}m ${s}s`
  return `${s}s`
}

const transitionOptions = ['cut', 'fade', 'dissolve', 'wipeleft', 'wiperight']

const copied = ref(null)
let copyTimeout = null

const logoPreviewStyle = computed(() => {
  const pos = overlayForm.logo_position
  const style = { position: 'absolute' }
  if (pos.includes('top')) style.top = '8px'; else style.bottom = '40px'
  if (pos.includes('left')) style.left = '8px'; else style.right = '8px'
  return style
})

const clockPreviewStyle = computed(() => {
  const pos = overlayForm.clock_position
  const style = { position: 'absolute' }
  if (pos.includes('top')) style.top = '8px'; else style.bottom = '8px'
  if (pos.includes('left')) style.left = '8px'; else style.right = '8px'
  return style
})

const lowerThirdPreviewStyle = computed(() => {
  const pos = overlayForm.lower_third_position
  const style = { position: 'absolute', background: overlayForm.lower_third_bg_color }
  style.bottom = '40px'
  if (pos === 'bottom-right') style.right = '16px'
  else if (pos === 'center') { style.left = '50%'; style.transform = 'translateX(-50%)' }
  else style.left = '16px'
  return style
})

function copyToClipboard(text, label) {
  if (navigator.clipboard && window.isSecureContext) {
    navigator.clipboard.writeText(text).then(() => {
      copied.value = label
      clearTimeout(copyTimeout)
      copyTimeout = setTimeout(() => { copied.value = null }, 2000)
    })
  } else {
    // Fallback for HTTP (non-secure context)
    const el = document.createElement('textarea')
    el.value = text
    el.style.position = 'fixed'
    el.style.opacity = '0'
    document.body.appendChild(el)
    el.focus()
    el.select()
    document.execCommand('copy')
    document.body.removeChild(el)
    copied.value = label
    clearTimeout(copyTimeout)
    copyTimeout = setTimeout(() => { copied.value = null }, 2000)
  }
}
</script>

<template>
  <AppLayout title="Channel Settings">
    <Head :title="channel.name" />

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg mb-6">
          <div class="p-6 border-b border-gray-200">
            <div class="flex items-center justify-between">
              <div>
                <div class="flex items-center gap-3">
                  <span class="inline-block w-4 h-4 rounded-full" :class="channel.is_live_streaming ? 'bg-green-500 animate-pulse' : channel.failover_active ? 'bg-yellow-500' : 'bg-gray-300'"></span>
                  <h1 class="text-2xl font-bold text-gray-900">{{ channel.name }}</h1>
                </div>
                <p class="text-sm text-gray-500 mt-1">
                  {{ channel.ingest_protocol.toUpperCase() }} &middot; Port {{ channel.ingest_port }} &middot;
                  {{ channel.is_live_streaming ? 'LIVE' : channel.failover_active ? 'FAILOVER' : 'OFFLINE' }}
                </p>
              </div>
            </div>
          </div>

          <div class="border-b border-gray-200">
            <nav class="flex overflow-x-auto">
              <button v-for="tab in tabs" :key="tab.key" @click="activeTab = tab.key"
                class="px-4 py-3 text-sm font-medium border-b-2 whitespace-nowrap transition-colors"
                :class="activeTab === tab.key ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'">
                {{ tab.label }}
              </button>
            </nav>
          </div>

          <!-- Overview Tab -->
          <div class="p-6" v-if="activeTab === 'overview'">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div class="bg-gray-50 rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Stream Status</h3>
                <dl class="space-y-3">
                  <div class="flex justify-between"><dt class="text-gray-600">Status</dt><dd :class="channel.is_live_streaming ? 'text-green-600 font-medium' : channel.failover_active ? 'text-yellow-600 font-medium' : 'text-gray-600'">{{ channel.is_live_streaming ? 'Live' : channel.failover_active ? 'VOD Failover' : 'Offline' }}</dd></div>
                  <div class="flex justify-between"><dt class="text-gray-600">Protocol</dt><dd class="text-gray-900">{{ channel.ingest_protocol.toUpperCase() }}</dd></div>
                  <div class="flex justify-between"><dt class="text-gray-600">Port</dt><dd class="text-gray-900">{{ channel.ingest_port }}</dd></div>
                  <div class="flex justify-between"><dt class="text-gray-600">Stream Key</dt><dd class="text-gray-900 font-mono text-xs truncate max-w-[120px]">{{ channel.stream_key }}</dd></div>
                  <button @click="copyToClipboard(channel.stream_key, 'stream_key')" class="mt-2 w-full text-xs text-indigo-600 hover:text-indigo-800 flex items-center justify-center gap-1 py-1 border border-indigo-200 rounded hover:bg-indigo-50 transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                    {{ copied === 'stream_key' ? 'Copied!' : 'Copy Stream Key' }}
                  </button>
                </dl>
              </div>
              <div class="bg-gray-50 rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Ingest URL</h3>
                <div class="bg-gray-900 rounded-lg p-4 flex items-start gap-2">
                  <code class="text-green-400 text-sm break-all flex-1">{{ ingestUrl }}</code>
                  <button @click="copyToClipboard(ingestUrl, 'url')" class="shrink-0 p-1.5 rounded hover:bg-gray-700 text-gray-400 hover:text-white transition-colors" title="Copy URL">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                  </button>
                </div>
                <p class="text-sm text-gray-500 mt-2">Use this in your encoder (OBS, vMix, FFmpeg, etc.)</p>
              </div>
            </div>
            <div class="mt-6 grid grid-cols-2 md:grid-cols-4 gap-4">
              <div class="bg-gray-50 rounded-lg p-4 text-center"><p class="text-2xl font-bold text-gray-900">{{ vod_items.length }}</p><p class="text-sm text-gray-500">VOD Items</p></div>
              <div class="bg-gray-50 rounded-lg p-4 text-center"><p class="text-2xl font-bold text-gray-900">{{ playlist_stats?.total_duration_formatted || '0s' }}</p><p class="text-sm text-gray-500">Total Duration</p></div>
              <div class="bg-gray-50 rounded-lg p-4 text-center"><p class="text-2xl font-bold text-gray-900">{{ health_logs.length }}</p><p class="text-sm text-gray-500">Logs</p></div>
              <div class="bg-gray-50 rounded-lg p-4 text-center"><p class="text-2xl font-bold" :class="overlay_settings?.enabled ? 'text-green-600' : 'text-gray-400'">{{ overlay_settings?.enabled ? 'ON' : 'OFF' }}</p><p class="text-sm text-gray-500">Overlay</p></div>
            </div>

            <!-- Output / Playback URLs -->
            <div class="mt-6">
              <h3 class="text-lg font-semibold text-gray-900 mb-3">Playback URLs (Flussonic)</h3>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div class="bg-gray-50 rounded-lg p-3 flex items-center justify-between">
                  <div>
                    <span class="text-xs font-semibold text-gray-500 uppercase">HLS</span>
                    <code class="text-sm text-gray-900 block truncate max-w-[280px]">{{ outputUrls.hls }}</code>
                  </div>
                  <button @click="copyToClipboard(outputUrls.hls, 'hls')" class="shrink-0 p-1.5 rounded hover:bg-gray-200 text-gray-400 hover:text-gray-600 transition-colors" title="Copy HLS URL">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                  </button>
                </div>
                <div class="bg-gray-50 rounded-lg p-3 flex items-center justify-between">
                  <div>
                    <span class="text-xs font-semibold text-gray-500 uppercase">RTMP</span>
                    <code class="text-sm text-gray-900 block truncate max-w-[280px]">{{ outputUrls.rtmp }}</code>
                  </div>
                  <button @click="copyToClipboard(outputUrls.rtmp, 'rtmp_out')" class="shrink-0 p-1.5 rounded hover:bg-gray-200 text-gray-400 hover:text-gray-600 transition-colors" title="Copy RTMP URL">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                  </button>
                </div>
                <div class="bg-gray-50 rounded-lg p-3 flex items-center justify-between">
                  <div>
                    <span class="text-xs font-semibold text-gray-500 uppercase">DASH</span>
                    <code class="text-sm text-gray-900 block truncate max-w-[280px]">{{ outputUrls.dash }}</code>
                  </div>
                  <button @click="copyToClipboard(outputUrls.dash, 'dash')" class="shrink-0 p-1.5 rounded hover:bg-gray-200 text-gray-400 hover:text-gray-600 transition-colors" title="Copy DASH URL">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                  </button>
                </div>
                <div class="bg-gray-50 rounded-lg p-3 flex items-center justify-between">
                  <div>
                    <span class="text-xs font-semibold text-gray-500 uppercase">Screenshot</span>
                    <code class="text-sm text-gray-900 block truncate max-w-[280px]">{{ outputUrls.screenshot }}</code>
                  </div>
                  <button @click="copyToClipboard(outputUrls.screenshot, 'screenshot')" class="shrink-0 p-1.5 rounded hover:bg-gray-200 text-gray-400 hover:text-gray-600 transition-colors" title="Copy Screenshot URL">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- Ingest Tab -->
          <div class="p-6" v-if="activeTab === 'ingest'">
            <h3 class="text-lg font-semibold text-gray-900 mb-6">Channel Settings</h3>
            <form @submit.prevent="handleChannelUpdate" class="space-y-5 max-w-lg">
              <div>
                <label class="block text-sm font-medium text-gray-700">Channel Name</label>
                <input v-model="channelForm.name" type="text" required
                  class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" />
                <p v-if="channelForm.errors.name" class="mt-1 text-xs text-red-600">{{ channelForm.errors.name }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Ingest Protocol</label>
                <select v-model="channelForm.ingest_protocol"
                  class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                  <option v-for="p in ingestProtocols" :key="p" :value="p">{{ p.toUpperCase() }}</option>
                </select>
                <p class="mt-1 text-xs text-gray-500">Changing protocol will reassign the ingest port automatically.</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Stream Key</label>
                <div class="mt-1 flex gap-2">
                  <input :value="channel.stream_key" readonly
                    class="block w-full border-gray-300 rounded-md shadow-sm bg-gray-50 font-mono text-sm text-gray-600" />
                  <button type="button" @click="copyToClipboard(channel.stream_key, 'key_ingest')"
                    class="shrink-0 px-3 py-2 border border-gray-300 rounded-md text-xs text-gray-600 hover:bg-gray-50">
                    {{ copied === 'key_ingest' ? 'Copied!' : 'Copy' }}
                  </button>
                </div>
                <p class="mt-1 text-xs text-gray-500">Stream key is auto-generated and cannot be changed.</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Ingest URL</label>
                <div class="mt-1 flex gap-2">
                  <input :value="ingestUrl" readonly
                    class="block w-full border-gray-300 rounded-md shadow-sm bg-gray-50 font-mono text-sm text-gray-600" />
                  <button type="button" @click="copyToClipboard(ingestUrl, 'url_ingest')"
                    class="shrink-0 px-3 py-2 border border-gray-300 rounded-md text-xs text-gray-600 hover:bg-gray-50">
                    {{ copied === 'url_ingest' ? 'Copied!' : 'Copy' }}
                  </button>
                </div>
              </div>
              <div class="flex items-center gap-3 pt-2">
                <button type="submit" :disabled="channelForm.processing"
                  class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 disabled:opacity-50">
                  {{ channelForm.processing ? 'Saving...' : 'Save Changes' }}
                </button>
                <span v-if="channelForm.wasSuccessful" class="text-sm text-green-600">Saved!</span>
              </div>
            </form>
          </div>

          <!-- Egress Tab -->
          <div class="p-6" v-if="activeTab === 'egress'">
            <h3 class="text-lg font-semibold text-gray-900 mb-6">Output Protocols</h3>
            <div class="space-y-3">
              <label v-for="p in availableProtocols" :key="p" class="flex items-center gap-3 p-3 border rounded-lg cursor-pointer hover:bg-gray-50">
                <input type="checkbox" :checked="channelForm.output_protocols_json.includes(p)" @change="toggleOutputProtocol(p)" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                <span class="text-sm font-medium text-gray-900">{{ p.toUpperCase() }}</span>
              </label>
            </div>
            <button @click="handleChannelUpdate" class="mt-6 inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">Save Outputs</button>
          </div>

          <!-- ==================== VOD PLAYLIST TAB ==================== -->
          <div class="p-6" v-if="activeTab === 'vod'">
            <!-- Playlist Settings Bar -->
            <div class="flex flex-wrap items-center gap-3 mb-6 p-4 bg-gray-50 rounded-lg">
              <div class="flex items-center gap-2">
                <span class="text-sm font-medium text-gray-700">Mode:</span>
                <select v-model="playlistSettings.playlist_mode" @change="savePlaylistSettings" class="border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                  <option value="sequential">Sequential</option>
                  <option value="shuffle">Shuffle</option>
                  <option value="scheduled">Scheduled</option>
                </select>
              </div>
              <div class="flex items-center gap-2">
                <span class="text-sm font-medium text-gray-700">Loop:</span>
                <button @click="playlistSettings.playlist_loop = !playlistSettings.playlist_loop; savePlaylistSettings()" :class="playlistSettings.playlist_loop ? 'bg-indigo-600' : 'bg-gray-300'" class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors">
                  <span :class="playlistSettings.playlist_loop ? 'translate-x-6' : 'translate-x-1'" class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"></span>
                </button>
              </div>
              <div class="flex items-center gap-2">
                <span class="text-sm font-medium text-gray-700">Fill Gap:</span>
                <select v-model="playlistSettings.playlist_fill_action" @change="savePlaylistSettings" class="border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                  <option value="black">Black Screen</option>
                  <option value="logo">Logo</option>
                  <option value="last_frame">Last Frame</option>
                </select>
              </div>
              <div class="ml-auto text-xs text-gray-500">
                {{ playlist_stats?.total_items }} items &middot; {{ playlist_stats?.total_duration_formatted }}
              </div>
            </div>

            <!-- Add Items Row -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
              <div class="border rounded-lg p-3">
                <h4 class="font-medium text-gray-900 mb-2 text-sm">Upload Video</h4>
                <form @submit.prevent="handleUpload" class="flex gap-2">
                  <div class="flex-1">
                    <input type="file" @input="uploadForm.file = $event.target.files[0]" accept="video/*" class="block w-full text-xs text-gray-500 file:mr-2 file:py-1 file:px-2 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100" />
                    <input v-model="uploadForm.title" type="text" placeholder="Title (optional)" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-xs" />
                  </div>
                  <button type="submit" :disabled="!uploadForm.file || uploadForm.processing" class="shrink-0 px-3 py-1.5 bg-indigo-600 rounded-md font-semibold text-xs text-white hover:bg-indigo-700 disabled:opacity-50">Upload</button>
                </form>
              </div>
              <div class="border rounded-lg p-3">
                <h4 class="font-medium text-gray-900 mb-2 text-sm">Add YouTube URL</h4>
                <form @submit.prevent="handleYoutubeAdd" class="flex gap-2">
                  <div class="flex-1">
                    <input v-model="youtubeForm.url" type="url" placeholder="https://youtube.com/watch?v=..." class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-xs" />
                    <input v-model="youtubeForm.title" type="text" placeholder="Title (optional)" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-xs" />
                  </div>
                  <button type="submit" :disabled="!youtubeForm.url || youtubeForm.processing" class="shrink-0 px-3 py-1.5 bg-indigo-600 rounded-md font-semibold text-xs text-white hover:bg-indigo-700 disabled:opacity-50">Add</button>
                </form>
              </div>
            </div>

            <!-- Playlist Items -->
            <div v-if="vod_items.length === 0" class="text-center py-12 text-gray-500 border-2 border-dashed border-gray-200 rounded-lg">
              <p class="text-lg">No items in playlist</p>
              <p class="text-sm">Upload videos or add YouTube links above to build your playlist.</p>
            </div>

            <div v-else class="space-y-1">
              <div v-for="item in vod_items" :key="item.id" class="border rounded-lg" :class="item.status === 'paused' ? 'opacity-50 bg-gray-50' : ''">
                <!-- Item Row -->
                <div class="flex items-start gap-3 p-3">
                  <!-- YouTube thumbnail -->
                  <div class="shrink-0">
                    <img v-if="item.type === 'youtube' && item.metadata_json?.thumbnail"
                      :src="item.metadata_json.thumbnail"
                      class="w-24 h-14 object-cover rounded bg-gray-800" />
                    <div v-else class="w-24 h-14 bg-gray-200 rounded flex items-center justify-center">
                      <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.069A1 1 0 0121 8.867v6.266a1 1 0 01-1.447.902L15 14M3 8a2 2 0 012-2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8z"/></svg>
                    </div>
                  </div>
                  <span class="text-xs text-gray-400 w-5 text-center mt-1">{{ item.order }}</span>
                  <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                      <span class="px-1.5 py-0.5 text-xs rounded font-mono shrink-0"
                        :class="item.type === 'youtube' ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-700'">
                        {{ item.type === 'youtube' ? 'YT' : 'FILE' }}
                      </span>
                      <p class="text-sm font-medium text-gray-900 truncate">{{ item.title || 'Untitled' }}</p>
                    </div>
                    <!-- YouTube meta details -->
                    <div v-if="item.type === 'youtube' && item.metadata_json" class="mt-1 flex flex-wrap gap-x-3 gap-y-0.5 text-xs text-gray-500">
                      <span v-if="item.metadata_json.channel">&#x1F4FA; {{ item.metadata_json.channel }}</span>
                      <span v-if="item.metadata_json.upload_date">&#x1F4C5; {{ item.metadata_json.upload_date }}</span>
                      <span v-if="item.metadata_json.view_count">&#x1F441; {{ Number(item.metadata_json.view_count).toLocaleString() }} views</span>
                      <span v-if="item.metadata_json.resolution">&#x1F4F9; {{ item.metadata_json.resolution }}</span>
                      <span v-if="item.metadata_json.fps">{{ item.metadata_json.fps }}fps</span>
                      <a v-if="item.metadata_json.webpage_url" :href="item.metadata_json.webpage_url" target="_blank" class="text-indigo-500 hover:underline">Open &#x2197;</a>
                    </div>
                    <!-- File/common meta -->
                    <p class="text-xs text-gray-500 mt-0.5">
                      <span>&#x23F1; {{ formatDuration((item.duration_override || item.duration_sec || 0) * (item.loop_count || 1)) }}</span>
                      <span v-if="item.loop_count > 1"> &middot; x{{ item.loop_count }}</span>
                      <span v-if="item.scheduled_at"> &middot; {{ item.scheduled_at }}</span>
                      <span v-if="item.transition !== 'cut'"> &middot; {{ item.transition }}</span>
                    </p>
                  </div>
                  <div class="flex items-center gap-2 shrink-0">
                    <button @click="toggleItemStatus(item)" class="text-xs" :class="item.status === 'active' ? 'text-green-600' : 'text-gray-400'" :title="item.status === 'active' ? 'Active - click to pause' : 'Paused - click to activate'">
                      {{ item.status === 'active' ? '&#9654;' : '&#9646;&#9646;' }}
                    </button>
                    <button @click="startEditItem(item)" class="text-xs text-indigo-600 hover:text-indigo-900">Edit</button>
                    <button @click="deleteVodItem(item.id)" class="text-xs text-red-500 hover:text-red-700">Del</button>
                  </div>
                </div>

                <!-- Edit Form (inline) -->
                <div v-if="editingItemId === item.id" class="px-4 pb-4 border-t border-gray-100 bg-gray-50 rounded-b-lg">
                  <form @submit.prevent="saveEditItem(item.id)" class="grid grid-cols-2 md:grid-cols-4 gap-3 pt-3">
                    <div>
                      <label class="block text-xs text-gray-600">Title</label>
                      <input v-model="editForm.title" type="text" class="mt-0.5 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-xs" />
                    </div>
                    <div>
                      <label class="block text-xs text-gray-600">Schedule (optional)</label>
                      <input v-model="editForm.scheduled_at" type="datetime-local" class="mt-0.5 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-xs" />
                    </div>
                    <div>
                      <label class="block text-xs text-gray-600">Duration Override (sec)</label>
                      <input v-model.number="editForm.duration_override" type="number" step="1" placeholder="Auto" class="mt-0.5 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-xs" />
                    </div>
                    <div>
                      <label class="block text-xs text-gray-600">Loop Count</label>
                      <input v-model.number="editForm.loop_count" type="number" min="1" max="99" class="mt-0.5 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-xs" />
                    </div>
                    <div>
                      <label class="block text-xs text-gray-600">Transition</label>
                      <select v-model="editForm.transition" class="mt-0.5 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-xs">
                        <option v-for="t in transitionOptions" :key="t" :value="t">{{ t }}</option>
                      </select>
                    </div>
                    <div>
                      <label class="block text-xs text-gray-600">Status</label>
                      <select v-model="editForm.status" class="mt-0.5 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-xs">
                        <option value="active">Active</option>
                        <option value="paused">Paused</option>
                      </select>
                    </div>
                    <div class="flex items-end gap-2">
                      <button type="submit" :disabled="editForm.processing" class="px-3 py-1.5 bg-indigo-600 rounded-md font-semibold text-xs text-white hover:bg-indigo-700 disabled:opacity-50">Save</button>
                      <button type="button" @click="cancelEditItem" class="px-3 py-1.5 bg-gray-300 rounded-md font-semibold text-xs text-gray-700 hover:bg-gray-400">Cancel</button>
                    </div>
                  </form>
                </div>
              </div>
            </div>

            <!-- Timeline Preview -->
            <div v-if="playlist_timeline?.length && playlistSettings.playlist_mode === 'scheduled'" class="mt-8">
              <h3 class="text-lg font-semibold text-gray-900 mb-4">Schedule Timeline</h3>
              <div class="space-y-2">
                <div v-for="(entry, i) in playlist_timeline" :key="i" class="flex items-center gap-3 p-2 bg-gray-50 rounded">
                  <div class="w-2 h-2 rounded-full" :class="entry.item.status === 'active' ? 'bg-green-500' : 'bg-gray-300'"></div>
                  <span class="text-sm font-medium text-gray-900 truncate flex-1">{{ entry.item.title || 'Untitled' }}</span>
                  <span class="text-xs text-gray-500">
                    {{ entry.start ? new Date(entry.start).toLocaleString() : 'Sequential' }}
                    <span v-if="entry.duration"> &rarr; {{ formatDuration(entry.duration) }}</span>
                  </span>
                </div>
              </div>
            </div>
          </div>

          <!-- Overlay Tab -->
          <div class="p-6" v-if="activeTab === 'overlay'">
            <h3 class="text-lg font-semibold text-gray-900 mb-2">Overlay Settings</h3>
            <p class="text-sm text-gray-500 mb-4">Applied during VOD failover. Preview updates live as you type.</p>

            <!-- Live Preview -->
            <div class="mb-6">
              <h4 class="text-sm font-semibold text-gray-700 mb-2">Live Preview</h4>
              <div class="relative bg-gray-900 rounded-lg overflow-hidden" style="aspect-ratio:16/9;max-width:640px">
                <div class="absolute inset-0 flex items-center justify-center">
                  <span class="text-gray-600 text-sm">[ Video Frame ]</span>
                </div>
                <!-- Logo preview -->
                <div v-if="overlay_settings?.logo_url && overlayForm.enabled"
                  class="absolute"
                  :style="logoPreviewStyle">
                  <img :src="overlay_settings.logo_url" :style="{width: overlayForm.logo_width + 'px'}" class="object-contain" />
                </div>
                <!-- Clock preview -->
                <div v-if="overlayForm.show_clock && overlayForm.enabled"
                  class="absolute text-white text-xs font-mono bg-black bg-opacity-50 px-1 rounded"
                  :style="clockPreviewStyle">
                  {{ new Date().toLocaleTimeString() }}
                </div>
                <!-- Ticker preview -->
                <div v-if="overlayForm.ticker_text && overlayForm.enabled"
                  class="absolute bottom-8 left-0 right-0 overflow-hidden py-1"
                  :style="{background: overlayForm.ticker_background_color, fontSize: overlayForm.ticker_font_size + 'px', color: overlayForm.ticker_font_color}">
                  <div class="whitespace-nowrap animate-marquee px-4">{{ overlayForm.ticker_text }}</div>
                </div>
                <!-- Crawl preview -->
                <div v-if="overlayForm.show_crawl && overlayForm.crawl_text && overlayForm.enabled"
                  class="absolute bottom-0 left-0 right-0 overflow-hidden py-1"
                  :style="{background: overlayForm.crawl_bg_color, fontSize: overlayForm.crawl_font_size + 'px', color: overlayForm.crawl_text_color}">
                  <div class="whitespace-nowrap px-4">{{ overlayForm.crawl_text }}</div>
                </div>
                <!-- Lower Third preview -->
                <div v-if="overlayForm.show_lower_third && overlayForm.lower_third_title && overlayForm.enabled"
                  class="absolute px-4 py-2 rounded"
                  :style="lowerThirdPreviewStyle">
                  <div :style="{fontSize: overlayForm.lower_third_font_size + 'px', color: overlayForm.lower_third_text_color, fontWeight: 'bold'}">{{ overlayForm.lower_third_title }}</div>
                  <div v-if="overlayForm.lower_third_subtitle" :style="{fontSize: (overlayForm.lower_third_font_size - 8) + 'px', color: overlayForm.lower_third_text_color, opacity: 0.85}">{{ overlayForm.lower_third_subtitle }}</div>
                </div>
              </div>
            </div>

            <form @submit.prevent="handleOverlaySave" class="space-y-4 max-w-2xl">
              <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                <span class="text-sm font-medium text-gray-700">Enable Overlay</span>
                <button type="button" @click="overlayForm.enabled = !overlayForm.enabled" :class="overlayForm.enabled ? 'bg-indigo-600' : 'bg-gray-300'" class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors">
                  <span :class="overlayForm.enabled ? 'translate-x-6' : 'translate-x-1'" class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"></span>
                </button>
              </div>

              <div v-if="overlayForm.enabled" class="space-y-4">
                <!-- Logo -->
                <div class="border rounded-lg p-4">
                  <h4 class="font-medium text-gray-900 mb-3">Logo</h4>
                  <div v-if="overlay_settings?.logo_url" class="mb-2"><img :src="overlay_settings.logo_url" class="h-12 object-contain bg-gray-800 rounded p-1" /></div>
                  <div class="grid grid-cols-2 gap-3">
                    <div><label class="block text-xs text-gray-600">Position</label>
                      <select v-model="overlayForm.logo_position" class="mt-0.5 block w-full border-gray-300 rounded-md text-xs">
                        <option value="top-left">Top Left</option><option value="top-right">Top Right</option>
                        <option value="bottom-left">Bottom Left</option><option value="bottom-right">Bottom Right</option>
                      </select>
                    </div>
                    <div><label class="block text-xs text-gray-600">Width (px)</label><input v-model.number="overlayForm.logo_width" type="number" min="10" max="500" class="mt-0.5 block w-full border-gray-300 rounded-md text-xs" /></div>
                  </div>
                  <form @submit.prevent="handleLogoUpload" class="mt-2">
                    <input type="file" @change="logoForm.logo = $event.target.files[0]" accept="image/png,image/jpeg,image/gif" class="block w-full text-xs text-gray-500 file:mr-2 file:py-1 file:px-2 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700" />
                    <button type="submit" :disabled="!logoForm.logo || logoForm.processing" class="mt-1 px-3 py-1 bg-gray-600 rounded font-semibold text-xs text-white hover:bg-gray-700 disabled:opacity-50">{{ logoForm.processing ? 'Uploading...' : 'Upload Logo' }}</button>
                    <span v-if="logoForm.wasSuccessful" class="ml-2 text-xs text-green-600">Uploaded!</span>
                  </form>
                </div>

                <!-- Ticker -->
                <div class="border rounded-lg p-4">
                  <h4 class="font-medium text-gray-900 mb-3">Ticker (Scrolling Text)</h4>
                  <div><label class="block text-xs text-gray-600">Text</label><input v-model="overlayForm.ticker_text" maxlength="500" placeholder="Breaking news..." class="mt-0.5 block w-full border-gray-300 rounded-md text-xs" /></div>
                  <div class="grid grid-cols-3 gap-3 mt-2">
                    <div><label class="block text-xs text-gray-600">Speed (px/s)</label><input v-model.number="overlayForm.ticker_speed" type="number" min="10" max="200" class="mt-0.5 block w-full border-gray-300 rounded-md text-xs" /></div>
                    <div><label class="block text-xs text-gray-600">Font Size</label><input v-model.number="overlayForm.ticker_font_size" type="number" min="12" max="72" class="mt-0.5 block w-full border-gray-300 rounded-md text-xs" /></div>
                    <div><label class="block text-xs text-gray-600">Direction</label>
                      <select v-model="overlayForm.ticker_direction" class="mt-0.5 block w-full border-gray-300 rounded-md text-xs">
                        <option value="left">Left</option><option value="right">Right</option>
                      </select>
                    </div>
                  </div>
                  <div class="grid grid-cols-2 gap-3 mt-2">
                    <div><label class="block text-xs text-gray-600">Background</label><div class="flex gap-1 mt-0.5"><input v-model="overlayForm.ticker_background_color" type="color" class="h-7 w-7 rounded border" /><input v-model="overlayForm.ticker_background_color" type="text" class="flex-1 border-gray-300 rounded-md text-xs font-mono" /></div></div>
                    <div><label class="block text-xs text-gray-600">Text Color</label><div class="flex gap-1 mt-0.5"><input v-model="overlayForm.ticker_font_color" type="color" class="h-7 w-7 rounded border" /><input v-model="overlayForm.ticker_font_color" type="text" class="flex-1 border-gray-300 rounded-md text-xs font-mono" /></div></div>
                  </div>
                </div>

                <!-- Crawl -->
                <div class="border rounded-lg p-4">
                  <div class="flex items-center justify-between mb-3">
                    <h4 class="font-medium text-gray-900">News Crawl (Bottom Strip)</h4>
                    <button type="button" @click="overlayForm.show_crawl = !overlayForm.show_crawl" :class="overlayForm.show_crawl ? 'bg-indigo-600' : 'bg-gray-300'" class="relative inline-flex h-5 w-10 items-center rounded-full transition-colors">
                      <span :class="overlayForm.show_crawl ? 'translate-x-5' : 'translate-x-1'" class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition-transform"></span>
                    </button>
                  </div>
                  <div v-if="overlayForm.show_crawl">
                    <div><label class="block text-xs text-gray-600">Crawl Text</label><textarea v-model="overlayForm.crawl_text" maxlength="1000" rows="2" placeholder="Latest news..." class="mt-0.5 block w-full border-gray-300 rounded-md text-xs"></textarea></div>
                    <div class="grid grid-cols-3 gap-3 mt-2">
                      <div><label class="block text-xs text-gray-600">Speed (px/s)</label><input v-model.number="overlayForm.crawl_speed" type="number" min="10" max="300" class="mt-0.5 block w-full border-gray-300 rounded-md text-xs" /></div>
                      <div><label class="block text-xs text-gray-600">Font Size</label><input v-model.number="overlayForm.crawl_font_size" type="number" min="12" max="72" class="mt-0.5 block w-full border-gray-300 rounded-md text-xs" /></div>
                    </div>
                    <div class="grid grid-cols-2 gap-3 mt-2">
                      <div><label class="block text-xs text-gray-600">Background</label><div class="flex gap-1 mt-0.5"><input v-model="overlayForm.crawl_bg_color" type="color" class="h-7 w-7 rounded border" /><input v-model="overlayForm.crawl_bg_color" type="text" class="flex-1 border-gray-300 rounded-md text-xs font-mono" /></div></div>
                      <div><label class="block text-xs text-gray-600">Text Color</label><div class="flex gap-1 mt-0.5"><input v-model="overlayForm.crawl_text_color" type="color" class="h-7 w-7 rounded border" /><input v-model="overlayForm.crawl_text_color" type="text" class="flex-1 border-gray-300 rounded-md text-xs font-mono" /></div></div>
                    </div>
                  </div>
                </div>

                <!-- Lower Third -->
                <div class="border rounded-lg p-4">
                  <div class="flex items-center justify-between mb-3">
                    <h4 class="font-medium text-gray-900">Lower Third</h4>
                    <button type="button" @click="overlayForm.show_lower_third = !overlayForm.show_lower_third" :class="overlayForm.show_lower_third ? 'bg-indigo-600' : 'bg-gray-300'" class="relative inline-flex h-5 w-10 items-center rounded-full transition-colors">
                      <span :class="overlayForm.show_lower_third ? 'translate-x-5' : 'translate-x-1'" class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition-transform"></span>
                    </button>
                  </div>
                  <div v-if="overlayForm.show_lower_third">
                    <div class="grid grid-cols-2 gap-3">
                      <div><label class="block text-xs text-gray-600">Title</label><input v-model="overlayForm.lower_third_title" maxlength="200" placeholder="Name / Headline" class="mt-0.5 block w-full border-gray-300 rounded-md text-xs" /></div>
                      <div><label class="block text-xs text-gray-600">Subtitle</label><input v-model="overlayForm.lower_third_subtitle" maxlength="200" placeholder="Role / Description" class="mt-0.5 block w-full border-gray-300 rounded-md text-xs" /></div>
                    </div>
                    <div class="grid grid-cols-3 gap-3 mt-2">
                      <div><label class="block text-xs text-gray-600">Position</label>
                        <select v-model="overlayForm.lower_third_position" class="mt-0.5 block w-full border-gray-300 rounded-md text-xs">
                          <option value="bottom-left">Bottom Left</option>
                          <option value="bottom-right">Bottom Right</option>
                          <option value="center">Center</option>
                        </select>
                      </div>
                      <div><label class="block text-xs text-gray-600">Font Size</label><input v-model.number="overlayForm.lower_third_font_size" type="number" min="16" max="72" class="mt-0.5 block w-full border-gray-300 rounded-md text-xs" /></div>
                      <div><label class="block text-xs text-gray-600">Show for (sec)</label><input v-model.number="overlayForm.lower_third_duration" type="number" min="1" max="30" class="mt-0.5 block w-full border-gray-300 rounded-md text-xs" /></div>
                    </div>
                    <div class="grid grid-cols-2 gap-3 mt-2">
                      <div><label class="block text-xs text-gray-600">Background</label><div class="flex gap-1 mt-0.5"><input v-model="overlayForm.lower_third_bg_color" type="color" class="h-7 w-7 rounded border" /><input v-model="overlayForm.lower_third_bg_color" type="text" class="flex-1 border-gray-300 rounded-md text-xs font-mono" /></div></div>
                      <div><label class="block text-xs text-gray-600">Text Color</label><div class="flex gap-1 mt-0.5"><input v-model="overlayForm.lower_third_text_color" type="color" class="h-7 w-7 rounded border" /><input v-model="overlayForm.lower_third_text_color" type="text" class="flex-1 border-gray-300 rounded-md text-xs font-mono" /></div></div>
                    </div>
                  </div>
                </div>

                <!-- Clock -->
                <div class="border rounded-lg p-4">
                  <div class="flex items-center justify-between mb-3">
                    <h4 class="font-medium text-gray-900">Clock</h4>
                    <button type="button" @click="overlayForm.show_clock = !overlayForm.show_clock" :class="overlayForm.show_clock ? 'bg-indigo-600' : 'bg-gray-300'" class="relative inline-flex h-5 w-10 items-center rounded-full transition-colors">
                      <span :class="overlayForm.show_clock ? 'translate-x-5' : 'translate-x-1'" class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition-transform"></span>
                    </button>
                  </div>
                  <div v-if="overlayForm.show_clock">
                    <div><label class="block text-xs text-gray-600">Position</label>
                      <select v-model="overlayForm.clock_position" class="mt-0.5 block w-full border-gray-300 rounded-md text-xs max-w-[180px]">
                        <option value="top-right">Top Right</option><option value="top-left">Top Left</option>
                        <option value="bottom-right">Bottom Right</option><option value="bottom-left">Bottom Left</option>
                      </select>
                    </div>
                  </div>
                </div>
              </div>

              <button type="submit" :disabled="overlayForm.processing" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 disabled:opacity-50">Save Overlay</button>
            </form>
          </div>

          <!-- Logs Tab -->
          <div class="p-6" v-if="activeTab === 'logs'">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Stream Health Logs</h3>
            <div v-if="health_logs.length === 0" class="text-center py-8 text-gray-500">No logs yet.</div>
            <div v-else class="space-y-2">
              <div v-for="log in health_logs" :key="log.id" class="p-3 border rounded-lg">
                <div class="flex items-center justify-between">
                  <span class="text-sm font-medium" :class="log.is_live ? 'text-green-600' : 'text-yellow-600'">{{ log.is_live ? 'LIVE' : 'FAILOVER' }}</span>
                  <span class="text-xs text-gray-400">{{ log.created_at }}</span>
                </div>
                <p class="text-sm text-gray-600 mt-1">{{ log.message }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
