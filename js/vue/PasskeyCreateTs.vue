<script setup lang="ts">

import { ref } from 'vue';
// https://simplewebauthn.dev/docs/packages/browser
// npm install @simplewebauthn/browser
import { startRegistration, startAuthentication } from '@simplewebauthn/browser';

import type { PublicKeyCredentialCreationOptionsJSON, AuthenticatorTransportFuture } from '@simplewebauthn/browser';

type PublicKeyCredentialSource = {
    id: number,
    name?: string|null,
    public_key_credential_id: Base64URLString,
    public_key_credential_id_hex: string,
    credential_public_key: string,
    aaguid: string,
    user_handle: string,
    counter: number,
    other_ui?: string[],
    backup_eligible: boolean,
    backup_status: boolean,
    usage_count: number,
    last_used_at?: string
}


const props = defineProps<{
    passkeys: PublicKeyCredentialSource[]
}>();

const working = ref<boolean>(false);
const registration_error = ref<string | null>(null);

function startWebauthnPasskeyRegistration() {
    return new Promise((resolve, reject) => {
        working.value = true

        // Fetch registration options
        window.axios.get('/passkeys/generate-registration-options')
            .then(async (response) => {
                console.log('Response:Registration Options', response)
                try {
                    // Parse the public key credential creation options
                    const publicKeyCredentialCreationOptions = JSON.parse(response.data.publicKeyCredentialCreationOptions || '{}') as PublicKeyCredentialCreationOptionsJSON;
                    
                    // Start registration process
                    const attResp = await startRegistration({ optionsJSON: publicKeyCredentialCreationOptions });

                    const r = {
                        attResp: attResp,
                        attRespJSON: JSON.stringify(attResp)
                    }

                    console.log('AttestationResponse: ', r)
                    
                    // Send the response back to the server for verification
                    const verificationResponse = await window.axios.post('/passkeys/verify-registration', { name: '', credentials: JSON.stringify(attResp) });
                    
                    // Reset working state and resolve
                    working.value = false

                    console.log('VerificationResponse: ', verificationResponse)

                    resolve(verificationResponse?.data);

                    window.location.reload();
                } catch (error: any) {
                    // Handle specific error types
                    let errorMessage = 'Unknown error occurred';
                    
                    if (error.name === 'InvalidStateError') {
                        errorMessage = 'Authenticator was probably already registered';
                    } else if (error.response) {
                        // Server responded with an error
                        errorMessage = error.response.data.message || 'Server error during registration';
                    } else if (error.request) {
                        // Request made but no response received
                        errorMessage = 'No response from server';
                    } else {
                        // Something happened in setting up the request
                        errorMessage = error.message;
                    }

                    working.value = false;
                    registration_error.value = errorMessage;
                    
                    reject(error);
                }
            })
            .catch(error => {
                // Handle axios request error
                working.value = false;
                registration_error.value = 'Failed to fetch registration options';
                
                reject(error);
            });
    });
}
</script>

<template>
      
    <div>
        <section :class="{ 'cursor-wait': working }">
            <header>
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    Passkeys
                </h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    If you have a compatible device, you can enable passwordless authentication (Face ID, Windows Hello or Biometric Authentication). To do this, you need to enable Passwordless Sign In on this device.
                </p>
            </header>

            <div class="mt-4 shadow-sm rounded-lg">
                <button type="button" :disabled="working" @click="startWebauthnPasskeyRegistration()" :class="{ 'opacity-25 cursor-not-allowed': working }" class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-500 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 disabled:opacity-25 transition ease-in-out duration-150">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1 1 21.75 8.25Z" />
                    </svg>
                    
                    Add Passkey
                </button>

                <!-- Error Message -->
                <template v-if="registration_error">
                    <div class="text-red-500 mt-2">
                        <p v-text="registration_error"></p>
                    </div>
                </template>
            </div>

            <div class="mt-6 bg-gray-300 dark:bg-gray-700 shadow-sm rounded-lg divide-y">
                <template v-if="passkeys.length > 0"  v-for="(passkey, i) in passkeys" :key="i">
                    <div class="flex-1 p-4">
                        <div class="flex justify-between items-center">
                            <div>
                                <span class="text-gray-800 dark:text-gray-200">{{ passkey.name ?? `Passkey #${i}` }}</span>
                            
                                <small v-if="passkey.last_used_at" class="ml-2 text-sm text-gray-600 dark:text-gray-300">
                                    {{ passkey.last_used_at }}
                                </small>
                            </div>
                        </div>
                    </div>
                </template>
                <div v-else class="p-4 text-gray-500">
                    No passkeys registered yet.'
                </div>
            </div>
        </section>
    </div>
</template>