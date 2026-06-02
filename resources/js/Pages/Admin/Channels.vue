<script setup>
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

defineProps({
  channels: Object,
  stats: Object,
})
</script>

<template>
  <AppLayout title="Channels">
    <Head title="Admin - Channels" />

    <div class="py-8">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <!-- Stats bar -->
        <div class="grid grid-cols-4 gap-3 mb-6">
          <div class="bg-white shadow-sm rounded-lg p-3 text-center">
            <p class="text-xl font-bold text-gray-900">{{ stats.total }}</p>
            <p class="text-xs text-gray-500">Total</p>
          </div>
          <div class="bg-white shadow-sm rounded-lg p-3 text-center">
            <p class="text-xl font-bold text-green-600">{{ stats.live }}</p>
            <p class="text-xs text-gray-500">Live</p>
          </div>
          <div class="bg-white shadow-sm rounded-lg p-3 text-center">
            <p class="text-xl font-bold text-yellow-600">{{ stats.failover }}</p>
            <p class="text-xs text-gray-500">Failover</p>
          </div>
          <div class="bg-white shadow-sm rounded-lg p-3 text-center">
            <p class="text-xl font-bold text-gray-500">{{ stats.offline }}</p>
            <p class="text-xs text-gray-500">Offline</p>
          </div>
        </div>

        <!-- Channels Table -->
        <div class="bg-white shadow-sm rounded-lg overflow-hidden">
          <div class="p-5 border-b border-gray-200">
            <h2 class="font-semibold text-gray-900">All Channels</h2>
          </div>

          <div v-if="channels.data.length === 0" class="p-8 text-center text-gray-500">No channels found.</div>

          <div v-else>
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Channel</th>
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Owner</th>
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Protocol</th>
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Port</th>
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">VOD</th>
                  <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-200">
                <tr v-for="channel in channels.data" :key="channel.id" class="hover:bg-gray-50">
                  <td class="px-4 py-3">
                    <span class="inline-flex items-center gap-1.5">
                      <span class="w-2 h-2 rounded-full" :class="channel.is_live_streaming ? 'bg-green-500 animate-pulse' : channel.failover_active ? 'bg-yellow-500' : 'bg-gray-300'"></span>
                      <span class="text-xs" :class="channel.is_live_streaming ? 'text-green-600' : channel.failover_active ? 'text-yellow-600' : 'text-gray-400'">
                        {{ channel.is_live_streaming ? 'Live' : channel.failover_active ? 'Failover' : 'Offline' }}
                      </span>
                    </span>
                  </td>
                  <td class="px-4 py-3">
                    <Link :href="route('admin.channels.show', { channel: channel.id })" class="text-sm font-medium text-gray-900 hover:text-indigo-600">{{ channel.name }}</Link>
                  </td>
                  <td class="px-4 py-3 text-sm text-gray-600">{{ channel.user?.name || '-' }}</td>
                  <td class="px-4 py-3">
                    <span class="px-2 py-0.5 text-xs rounded font-mono bg-gray-100 text-gray-700">{{ channel.ingest_protocol.toUpperCase() }}</span>
                  </td>
                  <td class="px-4 py-3 text-sm text-gray-600">{{ channel.ingest_port }}</td>
                  <td class="px-4 py-3 text-sm text-gray-500">{{ channel.vod_playlist_items_count || 0 }} items</td>
                  <td class="px-4 py-3 text-right">
                    <Link :href="route('admin.channels.show', { channel: channel.id })" class="text-sm text-indigo-600 hover:text-indigo-900">View &rarr;</Link>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div v-if="channels.links" class="px-5 py-3 border-t border-gray-200 flex justify-between">
            <Link v-if="channels.prev_page_url" :href="channels.prev_page_url" class="text-sm text-indigo-600 hover:text-indigo-900">&laquo; Previous</Link>
            <span v-else class="text-sm text-gray-400">&laquo; Previous</span>
            <Link v-if="channels.next_page_url" :href="channels.next_page_url" class="text-sm text-indigo-600 hover:text-indigo-900">Next &raquo;</Link>
            <span v-else class="text-sm text-gray-400">Next &raquo;</span>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
