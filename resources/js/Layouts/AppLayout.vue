<script setup>
import { ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'

defineProps({
  title: String,
})

const sidebarOpen = ref(false)

const logout = () => {
  router.post(route('logout'))
}
</script>

<template>
  <div>
    <Head :title="title" />

    <div class="min-h-screen flex bg-gray-50">
      <!-- Mobile backdrop -->
      <div
        v-if="sidebarOpen"
        class="fixed inset-0 z-20 bg-black/50 lg:hidden"
        @click="sidebarOpen = false"
      ></div>

      <!-- Sidebar -->
      <aside
        :class="[
          'fixed inset-y-0 left-0 z-30 w-64 bg-gray-900 text-white flex flex-col transform transition-transform duration-200 ease-in-out lg:translate-x-0 lg:static lg:inset-auto',
          sidebarOpen ? 'translate-x-0' : '-translate-x-full'
        ]"
      >
        <!-- Logo -->
        <div class="flex items-center gap-3 px-5 py-5 border-b border-gray-700">
          <svg class="w-8 h-8 text-indigo-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.75 10.5l4.72-4.72a.75.75 0 011.28.53v11.38a.75.75 0 01-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 002.25-2.25v-9a2.25 2.25 0 00-2.25-2.25h-9A2.25 2.25 0 002.25 7.5v9a2.25 2.25 0 002.25 2.25z" />
          </svg>
          <div>
            <h1 class="text-lg font-bold leading-tight">HybridStream</h1>
            <p class="text-xs text-gray-400">Streaming Core</p>
          </div>
        </div>

        <!-- Admin Navigation -->
        <nav v-if="$page.props.auth.user.is_admin" class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
          <p class="px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">Main</p>
          <Link
            :href="route('admin.dashboard')"
            :class="[
              'flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors',
              route().current('admin.dashboard')
                ? 'bg-indigo-600 text-white'
                : 'text-gray-300 hover:bg-gray-800 hover:text-white'
            ]"
          >
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />
            </svg>
            Dashboard
          </Link>

          <p class="px-3 pt-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Management</p>
          <Link
            :href="route('admin.users')"
            :class="[
              'flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors',
              route().current('admin.users*')
                ? 'bg-indigo-600 text-white'
                : 'text-gray-300 hover:bg-gray-800 hover:text-white'
            ]"
          >
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
            </svg>
            Users
          </Link>
          <Link
            :href="route('admin.channels')"
            :class="[
              'flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors',
              route().current('admin.channels*')
                ? 'bg-indigo-600 text-white'
                : 'text-gray-300 hover:bg-gray-800 hover:text-white'
            ]"
          >
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.75 10.5l4.72-4.72a.75.75 0 011.28.53v11.38a.75.75 0 01-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 002.25-2.25v-9a2.25 2.25 0 00-2.25-2.25h-9A2.25 2.25 0 002.25 7.5v9a2.25 2.25 0 002.25 2.25z" />
            </svg>
            Channels
          </Link>
          <Link
            :href="route('admin.subscriptions')"
            :class="[
              'flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors',
              route().current('admin.subscriptions*')
                ? 'bg-indigo-600 text-white'
                : 'text-gray-300 hover:bg-gray-800 hover:text-white'
            ]"
          >
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z" />
            </svg>
            Subscriptions
          </Link>

          <p class="px-3 pt-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">System</p>
          <Link
            :href="route('horizon.index')"
            target="_blank"
            class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium text-gray-300 hover:bg-gray-800 hover:text-white transition-colors"
          >
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M14.25 9.75L16.5 12l-2.25 2.25m-4.5 0L7.5 12l2.25-2.25M6 20.25h12A2.25 2.25 0 0020.25 18V6A2.25 2.25 0 0018 3.75H6A2.25 2.25 0 003.75 6v12A2.25 2.25 0 006 20.25z" />
            </svg>
            Horizon
          </Link>
        </nav>

        <!-- Channel User Navigation -->
        <nav v-else class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
          <Link
            :href="route('channel.dashboard')"
            :class="[
              'flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors',
              route().current('channel.dashboard')
                ? 'bg-indigo-600 text-white'
                : 'text-gray-300 hover:bg-gray-800 hover:text-white'
            ]"
          >
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />
            </svg>
            Dashboard
          </Link>

          <Link
            :href="route('channel.create')"
            :class="[
              'flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors',
              route().current('channel.create')
                ? 'bg-indigo-600 text-white'
                : 'text-gray-300 hover:bg-gray-800 hover:text-white'
            ]"
          >
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            New Channel
          </Link>

          <div v-if="$page.props.channels && $page.props.channels.length" class="pt-3">
            <p class="px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">My Channels</p>
            <Link
              v-for="channel in $page.props.channels"
              :key="channel.id"
              :href="route('channel.show', { channel: channel.id })"
              :class="[
                'flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-colors',
                route().current('channel.show', { channel: channel.id })
                  ? 'bg-indigo-600 text-white'
                  : 'text-gray-400 hover:bg-gray-800 hover:text-white'
              ]"
            >
              <span
                class="w-2 h-2 rounded-full shrink-0"
                :class="channel.is_live_streaming ? 'bg-green-400 animate-pulse' : channel.failover_active ? 'bg-yellow-400' : 'bg-gray-500'"
              ></span>
              <span class="truncate">{{ channel.name }}</span>
            </Link>
          </div>
        </nav>

        <!-- User Info & Logout -->
        <div class="border-t border-gray-700 p-3">
          <div class="flex items-center gap-3">
            <img
              v-if="$page.props.jetstream?.managesProfilePhotos"
              :src="$page.props.auth.user.profile_photo_url"
              class="w-8 h-8 rounded-full object-cover"
            />
            <div v-else class="w-8 h-8 rounded-full bg-indigo-500 flex items-center justify-center text-white text-sm font-medium">
              {{ $page.props.auth.user?.name?.charAt(0) || 'U' }}
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium text-white truncate">{{ $page.props.auth.user?.name }}</p>
              <p class="text-xs text-gray-400 truncate">{{ $page.props.auth.user?.email }}</p>
            </div>
          </div>
          <div class="mt-2 flex gap-1">
            <Link
              :href="route('profile.show')"
              class="flex-1 text-center px-2 py-1.5 text-xs text-gray-300 hover:text-white hover:bg-gray-800 rounded transition-colors"
            >
              Profile
            </Link>
            <button
              @click="logout"
              class="flex-1 text-center px-2 py-1.5 text-xs text-red-400 hover:text-red-300 hover:bg-gray-800 rounded transition-colors"
            >
              Logout
            </button>
          </div>
        </div>
      </aside>

      <!-- Main Content -->
      <div class="flex-1 flex flex-col min-w-0">
        <!-- Top bar (mobile) -->
        <header class="sticky top-0 z-10 bg-white border-b border-gray-200 lg:hidden">
          <div class="flex items-center justify-between h-14 px-4">
            <button
              @click="sidebarOpen = !sidebarOpen"
              class="p-2 -ml-2 text-gray-600 hover:text-gray-900 rounded-md"
            >
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path :class="sidebarOpen ? 'hidden' : ''" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                <path :class="sidebarOpen ? '' : 'hidden'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
            <h1 class="text-sm font-semibold text-gray-900 truncate">{{ title || 'Dashboard' }}</h1>
            <div class="w-6"></div>
          </div>
        </header>

        <!-- Page content -->
        <main class="flex-1">
          <slot />
        </main>
      </div>
    </div>
  </div>
</template>
