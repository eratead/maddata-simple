<x-app-layout>
    <main class="flex-1 w-full min-w-0 p-2 sm:p-4 md:p-8 bg-gray-50 min-h-screen">
        <div class="max-w-7xl mx-auto flex flex-col h-full">

            <!-- Split Header -->
            <div class="flex flex-col md:flex-row md:items-start justify-between gap-4 mb-6 md:mb-8">
                <div>
                    <!-- Breadcrumbs -->
                    <nav class="flex items-center text-[0.8rem] text-gray-400 mb-2 mt-4 md:mt-0 font-medium tracking-wide">
                        <a href="{{ route('dashboard') }}" class="hover:text-primary transition-colors">Dashboard</a>
                        <span class="mx-2 text-gray-300">/</span>
                        <span class="text-gray-600">Account Settings</span>
                    </nav>

                    <h1 class="text-2xl md:text-3xl font-bold text-gray-900 tracking-tight">Profile Settings</h1>
                    <p class="text-sm text-gray-500 mt-2">Manage your account information, security credentials, and data privacy.</p>
                </div>
            </div>

            <!-- Settings Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 lg:gap-8 items-start">
                
                <div class="flex flex-col gap-6 lg:gap-8">
                    <!-- Profile Info Card -->
                    <div class=" p-4 sm:p-6  md:p-8 bg-white rounded-xl shadow-sm border border-gray-100 hover:border-gray-200 transition-colors">
                        @include('profile.partials.update-profile-information-form')
                    </div>
                </div>

                <div class="flex flex-col gap-6 lg:gap-8">
                    <!-- Security Card -->
                    <div class=" p-4 sm:p-6  md:p-8 bg-white rounded-xl shadow-sm border border-gray-100 hover:border-gray-200 transition-colors">
                        @include('profile.partials.update-password-form')
                    </div>
                    
                    <!-- Danger Zone Card -->
                    <div class=" p-4 sm:p-6  md:p-8 bg-white rounded-xl shadow-sm border border-gray-100 hover:border-gray-200 transition-colors">
                        @include('profile.partials.delete-user-form')
                    </div>
                </div>

            </div>

        </div>
    </main>
</x-app-layout>
