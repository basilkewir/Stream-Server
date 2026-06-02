<script setup>
import { Head, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
  plans: Array,
})

const form = useForm({
  name: '',
  email: '',
  password: '',
  role: 'channel_user',
  subscription_plan_id: null,
})

function handleSubmit() {
  form.post(route('admin.users.store'))
}
</script>

<template>
  <AppLayout title="Create User">
    <Head title="Admin - Create User" />

    <div class="py-12">
      <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
          <div class="p-6 border-b border-gray-200">
            <h2 class="text-xl font-semibold text-gray-900">Create New User</h2>
          </div>
          <div class="p-6">
            <form @submit.prevent="handleSubmit" class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-gray-700">Name</label>
                <input v-model="form.name" required type="text" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Email</label>
                <input v-model="form.email" required type="email" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Password</label>
                <input v-model="form.password" required type="password" minlength="8" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" />
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
                  <option v-for="plan in plans" :key="plan.id" :value="plan.id">{{ plan.name }} - {{ plan.max_channels }} channels / {{ plan.storage_mb_limit }}MB / ${{ plan.price }}</option>
                </select>
              </div>
              <button type="submit" :disabled="form.processing" class="w-full inline-flex justify-center items-center px-4 py-3 bg-indigo-600 border border-transparent rounded-md font-semibold text-sm text-white uppercase tracking-widest hover:bg-indigo-700 disabled:opacity-50">
                Create User
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
