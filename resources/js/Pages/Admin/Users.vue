<script setup>
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

defineProps({
  users: Object,
})
</script>

<template>
  <AppLayout title="Users">
    <Head title="Admin - Users" />

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
          <div class="p-6 border-b border-gray-200 flex justify-between items-center">
            <h2 class="text-xl font-semibold text-gray-900">Channel Users</h2>
            <Link :href="route('admin.users.create')" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
              Create User
            </Link>
          </div>

          <div class="divide-y divide-gray-200">
            <div v-if="users.data.length === 0" class="p-6 text-center text-gray-500">No users found.</div>
            <div v-for="user in users.data" :key="user.id" class="p-4 hover:bg-gray-50">
              <div class="flex items-center justify-between">
                <div>
                  <p class="font-medium text-gray-900">{{ user.name }}</p>
                  <p class="text-sm text-gray-500">{{ user.email }}</p>
                </div>
                <div class="flex items-center gap-4">
                  <div class="text-right">
                    <p class="text-sm text-gray-600">{{ user.storage_used_mb }} / {{ user.subscription_plan?.storage_mb_limit || 500 }} MB</p>
                    <p class="text-xs text-gray-400">{{ user.channels_count }} / {{ user.subscription_plan?.max_channels || 5 }} channel(s)</p>
                  </div>
                  <span class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-800">
                    {{ user.subscription_plan?.name || 'No Plan' }}
                  </span>
                  <Link :href="route('admin.users.show', { user: user.id })" class="text-indigo-600 hover:text-indigo-900 ml-2">View</Link>
                </div>
              </div>
            </div>
          </div>

          <div v-if="users.links" class="p-4 border-t border-gray-200 flex justify-between">
            <Link v-if="users.prev_page_url" :href="users.prev_page_url" class="text-indigo-600 hover:text-indigo-900">&laquo; Previous</Link>
            <span v-else class="text-gray-400">&laquo; Previous</span>
            <Link v-if="users.next_page_url" :href="users.next_page_url" class="text-indigo-600 hover:text-indigo-900">Next &raquo;</Link>
            <span v-else class="text-gray-400">Next &raquo;</span>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
