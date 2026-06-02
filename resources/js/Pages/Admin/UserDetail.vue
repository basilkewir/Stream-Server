<script setup>
import { Head, useForm, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
  user: Object,
  plans: Array,
})

const form = useForm({
  name: props.user.name,
  email: props.user.email,
  password: '',
  subscription_plan_id: props.user.subscription_plan_id,
  subscription_expires_at: props.user.subscription_expires_at,
  role: props.user.role,
})

const extendForm = useForm({
  days: 30,
})

function handleUpdate() {
  form.put(route('admin.users.update', { user: props.user.id }))
}

function handleDelete() {
  if (confirm('Delete this user permanently? This cannot be undone.')) {
    router.delete(route('admin.users.destroy', { user: props.user.id }))
  }
}

function handleExtend() {
  extendForm.post(route('admin.users.extend', { user: props.user.id }))
}
</script>

<template>
  <AppLayout title="User Detail">
    <Head :title="'User - ' + user.name" />

    <div class="py-12">
      <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
          <div class="p-6 border-b border-gray-200 flex justify-between items-center">
            <div>
              <h2 class="text-xl font-semibold text-gray-900">{{ user.name }}</h2>
              <p class="text-sm text-gray-500">{{ user.email }}</p>
            </div>
            <button @click="handleDelete" class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700">
              Delete User
            </button>
          </div>

          <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
              <div>
                <h3 class="font-medium text-gray-900 mb-4">User Info</h3>
                <dl class="space-y-2">
                  <div class="flex justify-between p-2 bg-gray-50 rounded">
                    <dt class="text-gray-600 text-sm">Storage Used</dt>
                    <dd class="text-gray-900 text-sm">{{ user.storage_used_mb }} / {{ user.subscription_plan?.storage_mb_limit || 500 }} MB</dd>
                  </div>
                  <div class="flex justify-between p-2 bg-gray-50 rounded">
                    <dt class="text-gray-600 text-sm">Channels</dt>
                    <dd class="text-gray-900 text-sm">{{ user.channels?.length || 0 }} / {{ user.subscription_plan?.max_channels || 5 }} max</dd>
                  </div>
                  <div class="flex justify-between p-2 bg-gray-50 rounded">
                    <dt class="text-gray-600 text-sm">Role</dt>
                    <dd class="text-gray-900 text-sm font-medium">{{ user.role }}</dd>
                  </div>
                  <div class="flex justify-between p-2 bg-gray-50 rounded">
                    <dt class="text-gray-600 text-sm">Plan</dt>
                    <dd class="text-gray-900 text-sm">{{ user.subscription_plan?.name || 'None' }}</dd>
                  </div>
                  <div class="flex justify-between p-2 bg-gray-50 rounded">
                    <dt class="text-gray-600 text-sm">Expires</dt>
                    <dd class="text-gray-900 text-sm">{{ user.subscription_expires_at || 'Never' }}</dd>
                  </div>
                </dl>

                <div class="mt-6 border rounded-lg p-4">
                  <h4 class="font-medium text-gray-900 mb-3">Extend Subscription</h4>
                  <form @submit.prevent="handleExtend" class="flex gap-2">
                    <input v-model.number="extendForm.days" type="number" min="1" max="3650" class="block w-24 border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" />
                    <span class="flex items-center text-sm text-gray-500">days</span>
                    <button type="submit" :disabled="extendForm.processing" class="inline-flex items-center px-3 py-1.5 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 disabled:opacity-50">
                      Extend
                    </button>
                  </form>
                </div>
              </div>

              <div>
                <h3 class="font-medium text-gray-900 mb-4">Edit Settings</h3>
                <form @submit.prevent="handleUpdate" class="space-y-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700">Name</label>
                    <input v-model="form.name" type="text" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" />
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700">Email</label>
                    <input v-model="form.email" type="email" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" />
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700">New Password (leave empty to keep)</label>
                    <input v-model="form.password" type="password" minlength="8" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" />
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700">Role</label>
                    <select v-model="form.role" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                      <option value="channel_user">Channel User</option>
                      <option value="admin">Admin</option>
                    </select>
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700">Subscription Plan</label>
                    <select v-model="form.subscription_plan_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                      <option :value="null">None</option>
                      <option v-for="plan in plans" :key="plan.id" :value="plan.id">{{ plan.name }} ({{ plan.max_channels }} ch / {{ plan.storage_mb_limit }}MB)</option>
                    </select>
                  </div>
                  <button type="submit" :disabled="form.processing" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 disabled:opacity-50">
                    Update User
                  </button>
                </form>
              </div>
            </div>

            <div v-if="user.channels?.length" class="mt-8">
              <h3 class="font-medium text-gray-900 mb-4">User Channels</h3>
              <div class="space-y-2">
                <div v-for="channel in user.channels" :key="channel.id" class="p-3 border rounded-lg">
                  <div class="flex items-center justify-between">
                    <div>
                      <p class="font-medium text-gray-900">{{ channel.name }}</p>
                      <p class="text-sm text-gray-500">{{ channel.ingest_protocol.toUpperCase() }} :{{ channel.ingest_port }}</p>
                    </div>
                    <span :class="channel.is_live_streaming ? 'text-green-600' : channel.failover_active ? 'text-yellow-600' : 'text-gray-400'" class="text-sm">
                      {{ channel.is_live_streaming ? 'LIVE' : channel.failover_active ? 'FAILOVER' : 'Offline' }}
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
