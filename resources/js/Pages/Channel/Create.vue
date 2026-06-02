<script setup>
import { Head, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
  can_create: Boolean,
  channel_count: Number,
  max_channels: Number,
  has_active_subscription: Boolean,
})

const form = useForm({
  name: '',
  ingest_protocol: 'rtmp',
})

function handleSubmit() {
  form.post(route('channel.channels.store'))
}
</script>

<template>
  <AppLayout title="Create Channel">
    <Head title="Create Channel" />

    <div class="py-12">
      <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
          <div class="p-6 border-b border-gray-200">
            <h2 class="text-xl font-semibold text-gray-900">Create New Channel</h2>
            <p class="text-sm text-gray-500 mt-1">Channels: {{ channel_count }} / {{ max_channels }} used</p>
          </div>

          <div v-if="!can_create" class="p-6">
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
              <p class="text-yellow-800 font-medium">Channel limit reached</p>
              <p class="text-yellow-700 text-sm mt-1" v-if="!has_active_subscription">Your subscription has expired. Contact your admin to renew.</p>
              <p class="text-yellow-700 text-sm mt-1" v-else>You've used {{ channel_count }} of {{ max_channels }} available channels. Upgrade your plan to create more.</p>
            </div>
          </div>

          <div v-else class="p-6">
            <form @submit.prevent="handleSubmit" class="space-y-6">
              <div>
                <label class="block text-sm font-medium text-gray-700">Channel Name</label>
                <input v-model="form.name" type="text" required placeholder="My Channel" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" />
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700">Ingest Protocol</label>
                <select v-model="form.ingest_protocol" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                  <option value="rtmp">RTMP</option>
                  <option value="srt">SRT</option>
                  <option value="rtsp">RTSP</option>
                  <option value="mpegts">MPEG-TS</option>
                </select>
              </div>

              <button type="submit" :disabled="form.processing" class="w-full inline-flex justify-center items-center px-4 py-3 bg-indigo-600 border border-transparent rounded-md font-semibold text-sm text-white uppercase tracking-widest hover:bg-indigo-700 disabled:opacity-50">
                Create Channel
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
