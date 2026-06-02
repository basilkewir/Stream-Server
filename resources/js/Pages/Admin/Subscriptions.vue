<script setup>
import { ref } from 'vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
  plans: Array,
})

const showCreate = ref(false)
const editingPlan = ref(null)

const createForm = useForm({
  name: '',
  price: 0,
  storage_mb_limit: 500,
  max_channels: 5,
})

const editForm = useForm({
  name: '',
  price: 0,
  storage_mb_limit: 500,
  max_channels: 5,
})

function startEdit(plan) {
  editingPlan.value = plan.id
  editForm.name = plan.name
  editForm.price = plan.price
  editForm.storage_mb_limit = plan.storage_mb_limit
  editForm.max_channels = plan.max_channels
}

function cancelEdit() {
  editingPlan.value = null
}

function saveEdit(plan) {
  editForm.put(route('admin.subscriptions.update', { plan: plan.id }), {
    onSuccess: () => { editingPlan.value = null }
  })
}

function handleCreate() {
  createForm.post(route('admin.subscriptions.store'), {
    onSuccess: () => { showCreate.value = false; createForm.reset() }
  })
}

function deletePlan(planId) {
  if (confirm('Delete this subscription plan?')) {
    router.delete(route('admin.subscriptions.destroy', { plan: planId }))
  }
}
</script>

<template>
  <AppLayout title="Subscriptions">
    <Head title="Admin - Subscriptions" />

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
          <div class="p-6 border-b border-gray-200 flex justify-between items-center">
            <h2 class="text-xl font-semibold text-gray-900">Subscription Plans</h2>
            <button @click="showCreate = !showCreate" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
              {{ showCreate ? 'Cancel' : 'New Plan' }}
            </button>
          </div>

          <div v-if="showCreate" class="p-6 border-b border-gray-200 bg-gray-50">
            <h3 class="font-medium text-gray-900 mb-4">Create Plan</h3>
            <form @submit.prevent="handleCreate" class="space-y-4 max-w-lg">
              <div>
                <label class="block text-sm font-medium text-gray-700">Name</label>
                <input v-model="createForm.name" type="text" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" />
              </div>
              <div class="grid grid-cols-3 gap-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700">Price ($/mo)</label>
                  <input v-model.number="createForm.price" type="number" step="0.01" min="0" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" />
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700">Storage (MB)</label>
                  <input v-model.number="createForm.storage_mb_limit" type="number" min="1" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" />
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700">Max Channels</label>
                  <input v-model.number="createForm.max_channels" type="number" min="1" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" />
                </div>
              </div>
              <button type="submit" :disabled="createForm.processing" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 disabled:opacity-50">
                Create Plan
              </button>
            </form>
          </div>

          <div class="divide-y divide-gray-200">
            <div v-if="plans.length === 0" class="p-6 text-center text-gray-500">No subscription plans yet.</div>
            <div v-for="plan in plans" :key="plan.id" class="p-4 hover:bg-gray-50">
              <template v-if="editingPlan === plan.id">
                <form @submit.prevent="saveEdit(plan)" class="space-y-3">
                  <input v-model="editForm.name" type="text" required class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" />
                  <div class="grid grid-cols-3 gap-4">
                    <input v-model.number="editForm.price" type="number" step="0.01" min="0" required class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" placeholder="Price" />
                    <input v-model.number="editForm.storage_mb_limit" type="number" min="1" required class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" placeholder="Storage MB" />
                    <input v-model.number="editForm.max_channels" type="number" min="1" required class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" placeholder="Max Channels" />
                  </div>
                  <div class="flex gap-2">
                    <button type="submit" :disabled="editForm.processing" class="inline-flex items-center px-3 py-1.5 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">Save</button>
                    <button type="button" @click="cancelEdit" class="inline-flex items-center px-3 py-1.5 bg-gray-300 border border-transparent rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-400">Cancel</button>
                  </div>
                </form>
              </template>
              <template v-else>
                <div class="flex items-center justify-between">
                  <div class="flex-1">
                    <div class="flex items-center gap-4">
                      <p class="font-medium text-gray-900">{{ plan.name }}</p>
                      <span class="text-sm text-gray-500">${{ plan.price }}/mo</span>
                    </div>
                    <div class="flex gap-4 mt-1 text-xs text-gray-500">
                      <span>{{ plan.storage_mb_limit }} MB storage</span>
                      <span>{{ plan.max_channels }} channels max</span>
                      <span>{{ plan.users_count }} users</span>
                    </div>
                  </div>
                  <div class="flex gap-2">
                    <button @click="startEdit(plan)" class="text-indigo-600 hover:text-indigo-900 text-sm">Edit</button>
                    <button @click="deletePlan(plan.id)" class="text-red-500 hover:text-red-700 text-sm">Delete</button>
                  </div>
                </div>
              </template>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
