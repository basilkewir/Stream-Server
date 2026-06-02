<script setup>
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
  channels: Array,
  storage_used_mb: Number,
  storage_limit_mb: Number,
  storage_used_percent: Number,
  subscription: Object,
})
</script>

<template>
  <AppLayout title="Channel Dashboard">
    <Head title="Channel Dashboard" />

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
          <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
            <h3 class="text-lg font-medium text-gray-900">Storage</h3>
            <div class="mt-4">
              <div class="flex justify-between text-sm text-gray-600 mb-1">
                <span>{{ storage_used_mb }} MB used</span>
                <span>{{ storage_limit_mb }} MB limit</span>
              </div>
              <div class="w-full bg-gray-200 rounded-full h-2.5">
                <div
                  class="h-2.5 rounded-full"
                  :class="storage_used_percent > 90 ? 'bg-red-600' : storage_used_percent > 70 ? 'bg-yellow-500' : 'bg-green-600'"
                  :style="{ width: storage_used_percent + '%' }"
                ></div>
              </div>
            </div>
          </div>

          <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
            <h3 class="text-lg font-medium text-gray-900">Channels</h3>
            <div class="mt-4">
              <p class="text-3xl font-bold text-gray-900">{{ channels.length }}</p>
              <p class="text-sm text-gray-500 mt-1">Total Channels</p>
            </div>
            <div class="mt-2 space-y-1">
              <p class="text-sm text-gray-600">
                <span class="inline-block w-2 h-2 rounded-full bg-green-500 mr-1"></span>
                Live: {{ channels.filter(c => c.is_live_streaming).length }}
              </p>
              <p class="text-sm text-gray-600">
                <span class="inline-block w-2 h-2 rounded-full bg-yellow-500 mr-1"></span>
                Failover: {{ channels.filter(c => c.failover_active).length }}
              </p>
            </div>
          </div>

          <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
            <h3 class="text-lg font-medium text-gray-900">Subscription</h3>
            <div v-if="subscription" class="mt-4">
              <p class="text-xl font-semibold text-gray-900">{{ subscription.name }}</p>
              <p class="text-sm text-gray-500">${{ subscription.price }}/mo</p>
            </div>
            <div v-else class="mt-4">
              <p class="text-gray-500">No active plan</p>
            </div>
          </div>
        </div>

        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
          <div class="p-6 border-b border-gray-200 flex justify-between items-center">
            <h2 class="text-xl font-semibold text-gray-900">Your Channels</h2>
            <a :href="route('channel.create')" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
              Create Channel
            </a>
          </div>

          <div v-if="channels.length === 0" class="p-12 text-center text-gray-500">
            <p class="text-lg">No channels yet. Create your first channel to start streaming.</p>
          </div>

          <div v-else class="divide-y divide-gray-200">
            <div v-for="channel in channels" :key="channel.id" class="p-6 hover:bg-gray-50">
              <div class="flex items-center justify-between">
                <div>
                  <div class="flex items-center gap-3">
                    <span
                      class="inline-block w-3 h-3 rounded-full"
                      :class="channel.is_live_streaming ? 'bg-green-500 animate-pulse' : channel.failover_active ? 'bg-yellow-500' : 'bg-gray-300'"
                    ></span>
                    <h3 class="text-lg font-medium text-gray-900">{{ channel.name }}</h3>
                  </div>
                  <p class="text-sm text-gray-500 mt-1">
                    {{ channel.ingest_protocol.toUpperCase() }} / Port {{ channel.ingest_port }}
                  </p>
                </div>
                <div class="flex items-center gap-2">
                  <span class="px-2 py-1 text-xs rounded-full" :class="channel.is_live_streaming ? 'bg-green-100 text-green-800' : channel.failover_active ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800'">
                    {{ channel.is_live_streaming ? 'LIVE' : channel.failover_active ? 'FAILOVER' : 'OFFLINE' }}
                  </span>
                  <a :href="route('channel.show', { channel: channel.id })" class="text-indigo-600 hover:text-indigo-900 ml-4">
                    Manage &rarr;
                  </a>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
