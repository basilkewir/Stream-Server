<script setup>
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
  stats: Object,
  protocol_breakdown: Array,
  health_stats: Object,
  recent_health_logs: Array,
  channels: Array,
  server: Object,
})

const formatBytes = (gb) => Math.round(gb) + ' MB'
</script>

<template>
  <AppLayout title="Admin Dashboard">
    <Head title="Admin Dashboard" />

    <div class="py-8">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

        <!-- Top Stat Cards -->
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 mb-6">
          <div class="bg-white shadow-sm rounded-lg p-4 text-center border-l-4 border-indigo-500">
            <p class="text-2xl font-bold text-gray-900">{{ stats.totalUsers }}</p>
            <p class="text-xs text-gray-500 mt-0.5">Users</p>
          </div>
          <div class="bg-white shadow-sm rounded-lg p-4 text-center border-l-4 border-blue-500">
            <p class="text-2xl font-bold text-gray-900">{{ stats.totalChannels }}</p>
            <p class="text-xs text-gray-500 mt-0.5">Channels</p>
          </div>
          <div class="bg-white shadow-sm rounded-lg p-4 text-center border-l-4 border-green-500">
            <p class="text-2xl font-bold text-green-600">{{ stats.liveChannels }}</p>
            <p class="text-xs text-gray-500 mt-0.5">Live Now</p>
          </div>
          <div class="bg-white shadow-sm rounded-lg p-4 text-center border-l-4 border-yellow-500">
            <p class="text-2xl font-bold text-yellow-600">{{ stats.failoverChannels }}</p>
            <p class="text-xs text-gray-500 mt-0.5">Failover</p>
          </div>
          <div class="bg-white shadow-sm rounded-lg p-4 text-center border-l-4 border-purple-500">
            <p class="text-2xl font-bold text-gray-900">{{ formatBytes(stats.totalStorage) }}</p>
            <p class="text-xs text-gray-500 mt-0.5">Storage Used</p>
          </div>
          <div class="bg-white shadow-sm rounded-lg p-4 text-center border-l-4 border-teal-500">
            <p class="text-2xl font-bold text-gray-900">{{ stats.totalSubs }}</p>
            <p class="text-xs text-gray-500 mt-0.5">Active Plans</p>
          </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
          <!-- Protocol Breakdown -->
          <div class="bg-white shadow-sm rounded-lg p-5">
            <h3 class="font-semibold text-gray-900 mb-3">Protocol Breakdown</h3>
            <div class="space-y-2">
              <div v-for="p in protocol_breakdown" :key="p.ingest_protocol" class="flex items-center justify-between">
                <span class="text-sm text-gray-600 uppercase">{{ p.ingest_protocol }}</span>
                <div class="flex items-center gap-2">
                  <div class="w-24 bg-gray-200 rounded-full h-2">
                    <div class="bg-indigo-500 h-2 rounded-full" :style="{ width: ((p.count / stats.totalChannels) * 100) + '%' }"></div>
                  </div>
                  <span class="text-sm font-medium text-gray-900 w-6">{{ p.count }}</span>
                </div>
              </div>
              <div v-if="protocol_breakdown.length === 0" class="text-sm text-gray-400">No channels yet</div>
            </div>
          </div>

          <!-- Today's Events -->
          <div class="bg-white shadow-sm rounded-lg p-5">
            <h3 class="font-semibold text-gray-900 mb-3">Today's Activity</h3>
            <div class="grid grid-cols-3 gap-3">
              <div class="text-center p-3 bg-gray-50 rounded-lg">
                <p class="text-xl font-bold text-indigo-600">{{ health_stats.healthToday }}</p>
                <p class="text-xs text-gray-500">Health Checks</p>
              </div>
              <div class="text-center p-3 bg-yellow-50 rounded-lg">
                <p class="text-xl font-bold text-yellow-600">{{ health_stats.failoverToday }}</p>
                <p class="text-xs text-gray-500">Failovers</p>
              </div>
              <div class="text-center p-3 bg-green-50 rounded-lg">
                <p class="text-xl font-bold text-green-600">{{ health_stats.restoreToday }}</p>
                <p class="text-xs text-gray-500">Restored</p>
              </div>
            </div>
          </div>

          <!-- Server Info -->
          <div class="bg-white shadow-sm rounded-lg p-5">
            <h3 class="font-semibold text-gray-900 mb-3">Server Overview</h3>
            <dl class="space-y-1.5 text-xs">
              <div class="flex justify-between"><dt class="text-gray-500">OS</dt><dd class="text-gray-900">{{ server.server_os }}</dd></div>
              <div class="flex justify-between"><dt class="text-gray-500">PHP</dt><dd class="text-gray-900">{{ server.php_version }}</dd></div>
              <div class="flex justify-between"><dt class="text-gray-500">Laravel</dt><dd class="text-gray-900">{{ server.laravel_version }}</dd></div>
              <div class="flex justify-between"><dt class="text-gray-500">Disk Free</dt><dd class="text-gray-900">{{ server.disk_free }}</dd></div>
              <div class="flex justify-between"><dt class="text-gray-500">Disk Total</dt><dd class="text-gray-900">{{ server.disk_total }}</dd></div>
              <div class="flex justify-between"><dt class="text-gray-500">Memory Peak</dt><dd class="text-gray-900">{{ server.memory_usage }}</dd></div>
              <div class="flex justify-between"><dt class="text-gray-500">Server Time</dt><dd class="text-gray-900">{{ server.server_time }}</dd></div>
              <div class="flex justify-between"><dt class="text-gray-500">Uptime</dt><dd class="text-gray-900">{{ server.server_uptime }}</dd></div>
            </dl>
          </div>
        </div>

        <!-- Channels & Health Logs -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <div class="bg-white shadow-sm rounded-lg">
            <div class="p-5 border-b border-gray-200 flex justify-between items-center">
              <h2 class="font-semibold text-gray-900">Recent Channels</h2>
              <Link :href="route('admin.channels')" class="text-xs text-indigo-600 hover:text-indigo-900">View all &rarr;</Link>
            </div>
            <div class="divide-y divide-gray-100">
              <div v-if="channels.length === 0" class="p-5 text-center text-sm text-gray-400">No channels yet.</div>
              <div v-for="channel in channels" :key="channel.id" class="p-4 hover:bg-gray-50">
                <div class="flex items-center justify-between">
                  <div class="min-w-0">
                    <Link :href="route('admin.channels.show', { channel: channel.id })" class="font-medium text-sm text-gray-900 hover:text-indigo-600 truncate block">{{ channel.name }}</Link>
                    <p class="text-xs text-gray-500">{{ channel.user?.name || 'Unknown' }} &middot; {{ channel.ingest_protocol.toUpperCase() }}</p>
                  </div>
                  <div class="flex items-center gap-2">
                    <span class="text-xs text-gray-400">{{ channel.vod_playlist_items_count || 0 }} items</span>
                    <span class="px-2 py-0.5 text-xs rounded-full" :class="channel.is_live_streaming ? 'bg-green-100 text-green-700' : channel.failover_active ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-600'">
                      {{ channel.is_live_streaming ? 'LIVE' : channel.failover_active ? 'FAILOVER' : 'OFF' }}
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="bg-white shadow-sm rounded-lg">
            <div class="p-5 border-b border-gray-200">
              <h2 class="font-semibold text-gray-900">Stream Health Log</h2>
            </div>
            <div class="divide-y divide-gray-100 max-h-[500px] overflow-y-auto">
              <div v-if="recent_health_logs.length === 0" class="p-5 text-center text-sm text-gray-400">No events yet.</div>
              <div v-for="log in recent_health_logs" :key="log.id" class="p-3">
                <div class="flex items-center justify-between">
                  <span class="text-xs font-medium" :class="log.is_live ? 'text-green-600' : 'text-yellow-600'">
                    {{ log.is_live ? 'LIVE' : 'FAILOVER' }} &mdash; {{ log.channel?.name || 'Unknown' }}
                  </span>
                  <span class="text-xs text-gray-400">{{ log.created_at }}</span>
                </div>
                <p class="text-xs text-gray-500 mt-0.5">{{ log.message }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
