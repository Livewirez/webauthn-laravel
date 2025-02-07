import React, { useState } from 'react';
import axios from 'axios';
import { startAuthentication } from '@simplewebauthn/browser';

const PasskeyLogin = () => {
    const [working, setWorking] = useState(false);
    const [authenticationError, setAuthenticationError] = useState(null);

    const startWebauthnLogin = async () => {
        try {
            setWorking(true);

            const response = await axios.get('/passkeys/generate-authentication-options');
            const publicKeyCredentialRequestOptions = JSON.parse(
                response.data.publicKeyCredentialRequestOptions
            );

            const authResponse = await startAuthentication(publicKeyCredentialRequestOptions);

            const verificationResponse = await axios.post('/passkeys/verify-authentication', {
                credentials: JSON.stringify(authResponse),
                credentials_id: authResponse.id
            });

            if (verificationResponse.data.redirect) {
                window.location.href = verificationResponse.data.redirect;
            }

            return verificationResponse.data;
        } catch (error) {
            handleAuthenticationError(error);
        } finally {
            setWorking(false);
        }
    };

    const handleAuthenticationError = (error) => {
        let errorMessage = 'Authentication failed';

        if (error.response?.data) {
            errorMessage = extractErrorMessage(error.response.data);
        } else if (error.name === 'NotAllowedError') {
            errorMessage = 'Authentication was declined or timed out';
        } else if (error.name === 'SecurityError') {
            errorMessage = 'A security error occurred';
        } else {
            errorMessage = error.message || 'An unknown error occurred';
        }

        setAuthenticationError(errorMessage);
        console.error(error);
    };

    const extractErrorMessage = (responseData) => {
        if (responseData.credentials) {
            return Array.isArray(responseData.credentials) 
                ? responseData.credentials[0] 
                : responseData.credentials;
        }
        return responseData.message || 'Authentication failed';
    };

    function handleNetworkError(error, reject) {
        console.error('Network error during authentication:', error);
        
        const errorMessage = 'Unable to connect to authentication service. Please try again.';
        updateStoreWithError(errorMessage);
        reject(error);
    }
    

    return (
        <div>
            <section className={working ? 'cursor-wait' : ''}>
                <div className="mt-4 shadow-sm rounded-lg">
                    <button 
                        type="button"
                        disabled={working}
                        onClick={startWebauthnLogin}
                        className={`inline-flex items-center px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-500 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 disabled:opacity-25 transition ease-in-out duration-150 ${working ? 'opacity-25 cursor-not-allowed' : ''}`}
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor" className="size-6">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1 1 21.75 8.25Z" />
                        </svg>
                        Login with Passkey
                    </button>
                </div>

                {authenticationError && (
                    <div className="text-red-500 mt-2 shadow-sm rounded-lg">
                        <p>{authenticationError}</p>
                    </div>
                )}
            </section>
        </div>
    );
};

export default PasskeyLogin;