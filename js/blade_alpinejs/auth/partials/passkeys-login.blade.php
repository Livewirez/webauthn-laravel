<section x-data 
         x-bind:class="{ 'cursor-wait': $store.passkey_login.working }"
>

    <div class="mt-4 shadow-sm rounded-lg">
        <x-secondary-button 
            x-bind:disabled="$store.passkey_login.working"
            x-bind:class="{ 'opacity-25 cursor-not-allowed': $store.passkey_login.working }"
            x-on:click="startWebauthnLogin()"
        >
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1 1 21.75 8.25Z" />
            </svg>
          
            {{ __('Login with Passkey') }}
        </x-secondary-button>
    </div>

    <template x-if="$store.passkey_login.authentication_error">
        <div class="text-red-500 mt-2 shadow-sm rounded-lg">
            <p x-text="$store.passkey_login.authentication_error"></p>
        </div>
    </template>
</section>