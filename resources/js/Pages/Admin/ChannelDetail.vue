<script setup>
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

defineProps({
  channel: Object,
})
</script>

<template>
  <AppLayout title="Channel Detail">
    <Head :title="'Channel - ' + channel.name" />

    <div class="py-8">
      <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white shadow-sm rounded-lg">
          <div class="p-5 border-b border-gray-200 flex justify-between items-center">
            <div>
              <div class="flex items-center gap-3">
                <span class="w-3 h-3 rounded-full" :class="channel.is_live_streaming ? 'bg-green-500 animate-pulse' : channel.failover_active ? 'bg-yellow-500' : 'bg-gray-300'"></span>
                <h1 class="text-xl font-bold text-gray-900">{{ channel.name }}</h1>
              </div>
              <p class="text-xs text-gray-500 mt-1">
                Owner: {{ channel.user?.name }} ({{ channel.user?.email }})
                <span v-if="channel.user?.subscription_plan"> &middot; Plan: {{ channel.user.subscription_plan.name }}</span>
              </p>
            </div>
            <span class="px-3 py-1 text-xs rounded-full font-medium" :class="channel.is_live_streaming ? 'bg-green-100 text-green-700' : channel.failover_active ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-600'">
              {{ channel.is_live_streaming ? 'LIVE' : channel.failover_active ? 'FAILOVER' : 'OFFLINE' }}
            </span>
          </div>

          <div class="p-5">
            <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-6">
              <div class="bg-gray-50 rounded p-3">
                <p class="text-xs text-gray-500">Protocol</p>
                <p class="font-medium text-gray-900">{{ channel.ingest_protocol.toUpperCase() }}</p>
              </div>
              <div class="bg-gray-50 rounded p-3">
                <p class="text-xs text-gray-500">Port</p>
                <p class="font-medium text-gray-900">{{ channel.ingest_port }}</p>
              </div>
              <div class="bg-gray-50 rounded p-3">
                <p class="text-xs text-gray-500">Stream Key</p>
                <p class="font-medium text-gray-900 font-mono text-xs truncate">{{ channel.stream_key }}</p>
              </div>
              <div class="bg-gray-50 rounded p-3">
                <p class="text-xs text-gray-500">Output Protocols</p>
                <p class="font-medium text-gray-900">{{ (channel.output_protocols_json || []).join(', ').toUpperCase() || 'None' }}</p>
              </div>
              <div class="bg-gray-50 rounded p-3">
                <p class="text-xs text-gray-500">Last Live</p>
                <p class="font-medium text-gray-900">{{ channel.last_live_timestamp || 'Never' }}</p>
              </div>
              <div class="bg-gray-50 rounded p-3">
                <p class="text-xs text-gray-500">Playlist Mode</p>
                <p class="font-medium text-gray-900 capitalize">{{ channel.playlist_mode || 'Sequential' }}</p>
              </div>
            </div>

            <!-- VOD Items -->
            <h3 class="font-semibold text-gray-900 mb-3">VOD Playlist ({{ channel.vod_playlist_items?.length || 0 }} items)</h3>
            <div v-if="!channel.vod_playlist_items?.length" class="text-sm text-gray-400 mb-6">No playlist items.</div>
            <div v-else class="space-y-1 mb-6 max-h-64 overflow-y-auto">
              <div v-for="item in channel.vod_playlist_items" :key="item.id" class="flex items-center gap-3 p-2 border rounded text-sm">
                <span class="text-xs text-gray-400 w-5">{{ item.order }}</span>
                <span class="px-1.5 py-0.5 text-xs rounded font-mono" :class="item.type === 'youtube' ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-700'">{{ item.type === 'youtube' ? 'YT' : 'FILE' }}</span>
                <span class="flex-1 text-gray-900 truncate">{{ item.title || 'Untitled' }}</span>
                <span class="text-xs text-gray-500">{{ item.status }}</span>
              </div>
            </div>

            <!-- Health Logs -->
            <h3 class="font-semibold text-gray-900 mb-3">Recent Health Logs</h3>
            <div v-if="!channel.health_logs?.length" class="text-sm text-gray-400">No logs.</div>
            <div v-else class="space-y-1 max-h-64 overflow-y-auto">
              <div v-for="log in channel.health_logs.slice(0, 15)" :key="log.id" class="flex justify-between p-2 border rounded text-xs">
                <span :class="log.is_live ? 'text-green-600' : 'text-yellow-600'">{{ log.is_live ? 'LIVE' : 'FAILOVER' }}</span>
                <span class="text-gray-600">{{ log.message }}</span>
                <span class="text-gray-400">{{ log.created_at }}</span>
              </div>
            </div>
          </div>

          <div class="p-5 border-t border-gray-200 flex gap-3">
            <Link :href="route('admin.channels')" class="text-sm text-gray-600 hover:text-gray-900">&larr; All Channels</Link>
            <Link :href="route('admin.users.show', { user: channel.user_id })" class="text-sm text-indigo-600 hover:text-indigo-900">View Owner &rarr;</Link>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
