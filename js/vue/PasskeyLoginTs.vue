<script setup lang="ts">
import { ref } from 'vue';
// https://simplewebauthn.dev/docs/packages/browser
// npm install @simplewebauthn/browser
import { startRegistration, startAuthentication } from '@simplewebauthn/browser';

import type { PublicKeyCredentialRequestOptionsJSON } from '@simplewebauthn/types';

const working = ref<boolean>(false);
const authentication_error = ref<string | null>(null);

function startWebauthnLogin() {
    return new Promise((resolve, reject) => {
        // Initialize store state
        working.value = true

        // Fetch authentication options
        axios.get('/passkeys/generate-authentication-options')
            .then(async (response) => {
                console.log('Response:Login Options', response) 

                try {
                    // Parse authentication options
                    const publicKeyCredentialRequestOptions = JSON.parse(
                        response.data.publicKeyCredentialRequestOptions || '{}'
                    ) as PublicKeyCredentialRequestOptionsJSON;

                    // Start authentication process
                    const authResponse = await startAuthentication(
                        publicKeyCredentialRequestOptions
                    );

                    // Log authentication response for debugging
                    console.log('Authentication Response:', {
                        raw: authResponse,
                        serialized: JSON.stringify(authResponse)
                    });

                    // Verify authentication with server
                    const verificationResponse = await axios.post('/passkeys/verify-authentication', {
                        credentials: JSON.stringify(authResponse),
                        credentials_id: authResponse.id
                    });

                    // Reset store state on success
                    working.value = false

                    console.log('verificationResponse', verificationResponse) 

                    // Handle successful login
                    if (verificationResponse.data.redirect) {
                        window.location.href = verificationResponse.data.redirect;
                    }

                    resolve(verificationResponse.data);

                } catch (error) {
                    handleAuthenticationError(error, reject);
                }
            })
            .catch(error => handleNetworkError(error, reject));
    });
}
            

function handleAuthenticationError(error: Error|Record<string, any>, reject: Function) {
    let errorMessage = 'Authentication failed';

    if (error.response?.data) {
        // Handle structured error responses
        errorMessage = extractErrorMessage(error.response.data);
    } else if (error.name === 'NotAllowedError') {
        errorMessage = 'Authentication was declined or timed out';
    } else if (error.name === 'SecurityError') {
        errorMessage = 'A security error occurred';
    } else {
        errorMessage = error.message || 'An unknown error occurred';
    }

    updateStoreWithError(errorMessage);
    reject(error);
}

function handleNetworkError(error: Error|Record<string, any>, reject: Function) {
    console.error('Network error during authentication:', error);
    
    const errorMessage = 'Unable to connect to authentication service. Please try again.';
    updateStoreWithError(errorMessage);
    reject(error);
}


function extractErrorMessage(responseData: Record<string, any>) {
    if (responseData.credentials) {
        return Array.isArray(responseData.credentials) 
            ? responseData.credentials[0] 
            : responseData.credentials;
    }
    return responseData.message || 'Authentication failed';
}

function updateStoreWithError(errorMessage: string) {
    working.value = false;
    authentication_error.value = errorMessage;
}


</script>

<template>
    <div>
        <section :class="{ 'cursor-wait': working }">
            <div class="mt-4 shadow-sm rounded-lg">
                <button type="button" :disabled="working" @click="startWebauthnLogin()" :class="{ 'opacity-25 cursor-not-allowed': working }" class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-500 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 disabled:opacity-25 transition ease-in-out duration-150">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1 1 21.75 8.25Z" />
                    </svg>
                    
                    Login with Passkey
                </button>
            </div>
    
            <template v-if="authentication_error">
                <div class="text-red-500 mt-2 shadow-sm rounded-lg">
                    <p v-text="authentication_error"></p>
                </div>
            </template>
        </section>
    </div>
</template>