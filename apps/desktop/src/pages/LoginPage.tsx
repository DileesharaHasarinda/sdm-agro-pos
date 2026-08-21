import {
    useState,
} from 'react';

import type {
    FormEvent,
} from 'react';

import {
    useNavigate,
} from 'react-router';

import sdmLoginImage
    from '../assets/sdm-login.png';

import sdmLogo
    from '../assets/sdm-logo.png';

import { useAuth }
    from '../auth/AuthContext';

import { getRoleHome }
    from '../auth/roleHome';

import { ApiError }
    from '../lib/api';

const loginPageStyles = `
    #sdm-login-page,
    #sdm-login-page *,
    #sdm-login-page *::before,
    #sdm-login-page *::after {
        box-sizing: border-box !important;
    }

    #sdm-login-page {
        --sdm-green-950: #052e16;
        --sdm-green-900: #14532d;
        --sdm-green-800: #166534;
        --sdm-green-700: #15803d;
        --sdm-green-600: #16a34a;
        --sdm-green-500: #22c55e;
        --sdm-green-100: #dcfce7;
        --sdm-green-50: #f0fdf4;

        --sdm-text: #111827;
        --sdm-text-secondary: #374151;
        --sdm-muted: #6b7280;

        --sdm-border: #e5e7eb;
        --sdm-border-strong: #d1d5db;

        --sdm-red: #dc2626;
        --sdm-red-light: #fef2f2;

        --sdm-white: #ffffff;

        --sdm-font:
            -apple-system,
            BlinkMacSystemFont,
            "Segoe UI",
            Roboto,
            Helvetica,
            Arial,
            sans-serif;

        position: fixed !important;
        inset: 0 !important;

        display: flex !important;
        align-items: center !important;
        justify-content: center !important;

        width: 100vw !important;
        height: 100vh !important;
        height: 100dvh !important;

        margin: 0 !important;
        padding: 30px !important;

        overflow-x: hidden !important;
        overflow-y: auto !important;

        font-family: var(--sdm-font) !important;

        background: var(--sdm-green-950) !important;
    }

    #sdm-login-page h1,
    #sdm-login-page h2,
    #sdm-login-page h3,
    #sdm-login-page p,
    #sdm-login-page span,
    #sdm-login-page strong,
    #sdm-login-page label,
    #sdm-login-page input,
    #sdm-login-page button {
        font-family: var(--sdm-font) !important;
    }

    #sdm-login-page h1,
    #sdm-login-page h2,
    #sdm-login-page h3,
    #sdm-login-page p {
        margin: 0 !important;
        padding: 0 !important;
    }

    /* =========================================================
       BACKGROUND
       ========================================================= */

    #sdm-login-page .sdm-background {
        position: absolute !important;

        inset: 0 !important;

        width: 100% !important;
        height: 100% !important;

        overflow: hidden !important;

        z-index: 0 !important;
    }

    #sdm-login-page .sdm-background-image {
        position: absolute !important;

        inset: 0 !important;

        width: 100% !important;
        height: 100% !important;

        object-fit: cover !important;
        object-position: center center !important;

        transform: scale(1.025) !important;

        user-select: none !important;
        pointer-events: none !important;
    }

    #sdm-login-page .sdm-background-overlay {
        position: absolute !important;

        inset: 0 !important;

        background:
            linear-gradient(
                135deg,
                rgba(2, 25, 13, 0.80) 0%,
                rgba(5, 46, 22, 0.56) 40%,
                rgba(8, 53, 30, 0.45) 62%,
                rgba(2, 25, 13, 0.78) 100%
            ) !important;
    }

    #sdm-login-page .sdm-background-vignette {
        position: absolute !important;

        inset: 0 !important;

        background:
            radial-gradient(
                circle at center,
                rgba(255, 255, 255, 0.05) 0%,
                transparent 36%,
                rgba(0, 0, 0, 0.22) 100%
            ) !important;
    }

    #sdm-login-page .sdm-background-glow {
        position: absolute !important;

        top: -180px !important;
        left: 50% !important;

        width: 700px !important;
        height: 500px !important;

        transform: translateX(-50%) !important;

        border-radius: 50% !important;

        background:
            radial-gradient(
                circle,
                rgba(187, 247, 208, 0.16) 0%,
                rgba(134, 239, 172, 0.06) 42%,
                transparent 70%
            ) !important;

        filter: blur(15px) !important;
    }

    /* =========================================================
       CENTER WRAPPER
       ========================================================= */

    #sdm-login-page .sdm-login-wrapper {
        position: relative !important;

        z-index: 5 !important;

        display: flex !important;
        flex-direction: column !important;
        align-items: center !important;
        justify-content: center !important;

        width: 100% !important;
        max-width: 470px !important;

        margin: auto !important;
    }

    /* =========================================================
       LOGIN CARD
       ========================================================= */

    #sdm-login-page .sdm-login-card {
        position: relative !important;

        display: flex !important;
        flex-direction: column !important;

        width: 100% !important;

        padding:
            0
            38px
            34px !important;

        overflow: hidden !important;

        background:
            linear-gradient(
                145deg,
                rgba(255, 255, 255, 0.97),
                rgba(249, 253, 250, 0.94)
            ) !important;

        border:
            1px solid
            rgba(255, 255, 255, 0.72) !important;

        border-radius: 24px !important;

        box-shadow:
            0 40px 100px
            rgba(0, 0, 0, 0.30),
            0 12px 30px
            rgba(0, 0, 0, 0.12),
            inset 0 1px 0
            rgba(255, 255, 255, 0.80) !important;

        backdrop-filter:
            blur(24px)
            saturate(130%) !important;
    }

    #sdm-login-page .sdm-card-top-line {
        position: absolute !important;

        top: 0 !important;
        left: 50% !important;

        width: 120px !important;
        height: 4px !important;

        transform: translateX(-50%) !important;

        background:
            linear-gradient(
                90deg,
                var(--sdm-green-600),
                #65a30d
            ) !important;

        border-radius:
            0
            0
            8px
            8px !important;
    }

    /* =========================================================
       LOGO
       ========================================================= */

    #sdm-login-page .sdm-logo-section {
        display: flex !important;
        flex-direction: column !important;
        align-items: center !important;

        width: 100% !important;

        padding-top: 34px !important;

        text-align: center !important;
    }

    #sdm-login-page .sdm-logo-container {
        position: relative !important;

        display: flex !important;
        align-items: center !important;
        justify-content: center !important;

        width: 88px !important;
        height: 88px !important;

        margin-bottom: 15px !important;

        padding: 9px !important;

        border-radius: 22px !important;

        background:
            linear-gradient(
                145deg,
                #ffffff,
                #f3faf5
            ) !important;

        border:
            1px solid
            rgba(21, 128, 61, 0.13) !important;

        box-shadow:
            0 12px 28px
            rgba(20, 83, 45, 0.13),
            inset 0 1px 0
            rgba(255, 255, 255, 0.95) !important;
    }

    #sdm-login-page .sdm-logo-container::after {
        content: "" !important;

        position: absolute !important;

        inset: -6px !important;

        z-index: -1 !important;

        border:
            1px solid
            rgba(34, 197, 94, 0.12) !important;

        border-radius: 27px !important;
    }

    #sdm-login-page .sdm-logo-image {
        display: block !important;

        width: 100% !important;
        height: 100% !important;

        object-fit: contain !important;

        user-select: none !important;
        pointer-events: none !important;
    }

    #sdm-login-page .sdm-brand-name {
        font-size: 23px !important;
        line-height: 1.25 !important;

        font-weight: 780 !important;

        letter-spacing: -0.035em !important;

        color: var(--sdm-text) !important;
    }

    #sdm-login-page .sdm-brand-description {
        margin-top: 5px !important;

        font-size: 12.5px !important;
        font-weight: 500 !important;

        color: var(--sdm-muted) !important;
    }

    /* =========================================================
       DIVIDER
       ========================================================= */

    #sdm-login-page .sdm-section-divider {
        display: flex !important;
        align-items: center !important;

        width: 100% !important;

        margin:
            23px
            0
            24px !important;

        gap: 12px !important;
    }

    #sdm-login-page .sdm-section-divider::before,
    #sdm-login-page .sdm-section-divider::after {
        content: "" !important;

        flex: 1 !important;

        height: 1px !important;

        background:
            linear-gradient(
                90deg,
                transparent,
                var(--sdm-border),
                transparent
            ) !important;
    }

    #sdm-login-page .sdm-secure-badge {
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;

        gap: 6px !important;

        white-space: nowrap !important;

        font-size: 10.5px !important;
        font-weight: 700 !important;

        letter-spacing: 0.075em !important;
        text-transform: uppercase !important;

        color: var(--sdm-green-700) !important;
    }

    #sdm-login-page .sdm-secure-dot {
        width: 6px !important;
        height: 6px !important;

        border-radius: 50% !important;

        background: var(--sdm-green-500) !important;

        box-shadow:
            0 0 0 4px
            rgba(34, 197, 94, 0.10) !important;
    }

    /* =========================================================
       HEADER
       ========================================================= */

    #sdm-login-page .sdm-login-header {
        display: flex !important;
        flex-direction: column !important;

        width: 100% !important;

        margin-bottom: 24px !important;

        text-align: left !important;
    }

    #sdm-login-page .sdm-login-header h1 {
        font-size: 24px !important;
        line-height: 1.3 !important;

        font-weight: 740 !important;

        letter-spacing: -0.03em !important;

        color: var(--sdm-text) !important;
    }

    #sdm-login-page .sdm-login-header p {
        margin-top: 6px !important;

        font-size: 13px !important;
        line-height: 1.55 !important;

        color: var(--sdm-muted) !important;
    }

    /* =========================================================
       ERROR
       ========================================================= */

    #sdm-login-page .sdm-login-alert {
        display: flex !important;
        align-items: flex-start !important;

        width: 100% !important;

        margin-bottom: 17px !important;

        padding:
            11px
            13px !important;

        gap: 9px !important;

        font-size: 12.5px !important;
        font-weight: 500 !important;

        line-height: 1.5 !important;

        color: var(--sdm-red) !important;

        background: var(--sdm-red-light) !important;

        border:
            1px solid
            #fecaca !important;

        border-radius: 10px !important;
    }

    #sdm-login-page .sdm-alert-icon {
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;

        flex-shrink: 0 !important;

        width: 18px !important;
        height: 18px !important;

        margin-top: 1px !important;
    }

    #sdm-login-page .sdm-alert-icon svg {
        width: 17px !important;
        height: 17px !important;

        fill: none !important;
        stroke: currentColor !important;

        stroke-width: 2 !important;
        stroke-linecap: round !important;
        stroke-linejoin: round !important;
    }

    /* =========================================================
       FORM
       ========================================================= */

    #sdm-login-page .sdm-login-form {
        display: flex !important;
        flex-direction: column !important;

        width: 100% !important;

        gap: 18px !important;
    }

    #sdm-login-page .sdm-field {
        display: flex !important;
        flex-direction: column !important;

        width: 100% !important;

        gap: 7px !important;
    }

    #sdm-login-page .sdm-field-label {
        font-size: 12.5px !important;
        font-weight: 650 !important;

        color: var(--sdm-text-secondary) !important;
    }

    #sdm-login-page .sdm-input-wrapper {
        position: relative !important;

        display: flex !important;
        align-items: center !important;

        width: 100% !important;
    }

    #sdm-login-page .sdm-input-icon {
        position: absolute !important;

        left: 14px !important;

        z-index: 2 !important;

        display: flex !important;
        align-items: center !important;
        justify-content: center !important;

        width: 19px !important;
        height: 19px !important;

        color: #8b949f !important;

        pointer-events: none !important;

        transition:
            color 0.15s ease !important;
    }

    #sdm-login-page .sdm-input-icon svg {
        display: block !important;

        width: 18px !important;
        height: 18px !important;

        fill: none !important;
        stroke: currentColor !important;

        stroke-width: 1.8 !important;
        stroke-linecap: round !important;
        stroke-linejoin: round !important;
    }

    #sdm-login-page .sdm-input-wrapper:focus-within
        .sdm-input-icon {
        color: var(--sdm-green-700) !important;
    }

    #sdm-login-page .sdm-input {
        display: block !important;

        width: 100% !important;
        height: 50px !important;

        padding:
            0
            15px
            0
            44px !important;

        font-size: 14px !important;
        font-weight: 450 !important;

        color: var(--sdm-text) !important;

        background:
            rgba(255, 255, 255, 0.86) !important;

        border:
            1px solid
            var(--sdm-border-strong) !important;

        border-radius: 11px !important;

        outline: none !important;

        transition:
            border-color 0.15s ease,
            box-shadow 0.15s ease,
            background-color 0.15s ease !important;
    }

    #sdm-login-page .sdm-input::placeholder {
        color: #9ca3af !important;
        opacity: 1 !important;
    }

    #sdm-login-page .sdm-input:hover:not(:disabled) {
        border-color: #b4bbc4 !important;
    }

    #sdm-login-page .sdm-input:focus {
        background: #ffffff !important;

        border-color:
            var(--sdm-green-600) !important;

        box-shadow:
            0 0 0 4px
            rgba(22, 163, 74, 0.10) !important;
    }

    #sdm-login-page .sdm-input:disabled {
        color: var(--sdm-muted) !important;

        background: #f9fafb !important;

        cursor: not-allowed !important;
    }

    /* =========================================================
       PASSWORD
       ========================================================= */

    #sdm-login-page .sdm-password-input {
        padding-right: 52px !important;
    }

    #sdm-login-page .sdm-password-toggle {
        position: absolute !important;

        top: 50% !important;
        right: 7px !important;

        z-index: 3 !important;

        transform: translateY(-50%) !important;

        display: flex !important;
        align-items: center !important;
        justify-content: center !important;

        width: 36px !important;
        height: 36px !important;

        margin: 0 !important;
        padding: 0 !important;

        color: #77818c !important;

        background: transparent !important;

        border: none !important;
        border-radius: 8px !important;

        outline: none !important;

        cursor: pointer !important;

        transition:
            color 0.15s ease,
            background-color 0.15s ease !important;
    }

    #sdm-login-page .sdm-password-toggle:hover:not(:disabled) {
        color: var(--sdm-green-700) !important;

        background: var(--sdm-green-50) !important;
    }

    #sdm-login-page .sdm-password-toggle:focus-visible {
        color: var(--sdm-green-700) !important;

        background: var(--sdm-green-50) !important;

        box-shadow:
            0 0 0 2px
            rgba(22, 163, 74, 0.15) !important;
    }

    #sdm-login-page .sdm-password-toggle:disabled {
        opacity: 0.5 !important;

        cursor: not-allowed !important;
    }

    #sdm-login-page .sdm-password-toggle svg {
        display: block !important;

        width: 19px !important;
        height: 19px !important;

        fill: none !important;
        stroke: currentColor !important;

        stroke-width: 1.8 !important;
        stroke-linecap: round !important;
        stroke-linejoin: round !important;
    }

    /* =========================================================
       LOGIN BUTTON
       ========================================================= */

    #sdm-login-page .sdm-login-button {
        position: relative !important;

        display: flex !important;
        align-items: center !important;
        justify-content: center !important;

        width: 100% !important;
        height: 51px !important;

        margin-top: 4px !important;
        padding:
            0
            17px !important;

        overflow: hidden !important;

        gap: 9px !important;

        font-size: 14px !important;
        font-weight: 700 !important;

        letter-spacing: 0.01em !important;

        color: #ffffff !important;

        background:
            linear-gradient(
                135deg,
                #15803d 0%,
                #166534 54%,
                #14532d 100%
            ) !important;

        border:
            1px solid
            rgba(20, 83, 45, 0.90) !important;

        border-radius: 11px !important;

        box-shadow:
            0 10px 24px
            rgba(21, 128, 61, 0.22),
            inset 0 1px 0
            rgba(255, 255, 255, 0.14) !important;

        outline: none !important;

        cursor: pointer !important;

        transition:
            transform 0.15s ease,
            box-shadow 0.15s ease,
            opacity 0.15s ease !important;
    }

    #sdm-login-page .sdm-login-button::before {
        content: "" !important;

        position: absolute !important;

        top: 0 !important;
        left: -80px !important;

        width: 50px !important;
        height: 100% !important;

        transform: skewX(-20deg) !important;

        background:
            linear-gradient(
                90deg,
                transparent,
                rgba(255, 255, 255, 0.14),
                transparent
            ) !important;

        transition:
            left 0.45s ease !important;
    }

    #sdm-login-page .sdm-login-button:hover:not(:disabled)::before {
        left: calc(100% + 40px) !important;
    }

    #sdm-login-page .sdm-login-button:hover:not(:disabled) {
        transform: translateY(-1px) !important;

        box-shadow:
            0 14px 30px
            rgba(21, 128, 61, 0.27),
            inset 0 1px 0
            rgba(255, 255, 255, 0.14) !important;
    }

    #sdm-login-page .sdm-login-button:active:not(:disabled) {
        transform: translateY(0) !important;
    }

    #sdm-login-page .sdm-login-button:disabled {
        opacity: 0.67 !important;

        cursor: not-allowed !important;

        transform: none !important;
    }

    #sdm-login-page .sdm-button-content {
        position: relative !important;

        z-index: 2 !important;

        display: flex !important;
        align-items: center !important;
        justify-content: center !important;

        gap: 9px !important;
    }

    #sdm-login-page .sdm-button-arrow {
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;

        width: 18px !important;
        height: 18px !important;
    }

    #sdm-login-page .sdm-button-arrow svg {
        width: 17px !important;
        height: 17px !important;

        fill: none !important;
        stroke: currentColor !important;

        stroke-width: 2 !important;
        stroke-linecap: round !important;
        stroke-linejoin: round !important;
    }

    /* =========================================================
       SPINNER
       ========================================================= */

    #sdm-login-page .sdm-spinner {
        display: inline-block !important;

        width: 17px !important;
        height: 17px !important;

        border:
            2px solid
            rgba(255, 255, 255, 0.34) !important;

        border-top-color: #ffffff !important;

        border-radius: 50% !important;

        animation:
            sdm-login-spin
            0.7s
            linear
            infinite !important;
    }

    @keyframes sdm-login-spin {
        to {
            transform: rotate(360deg);
        }
    }

    /* =========================================================
       CARD BOTTOM
       ========================================================= */

    #sdm-login-page .sdm-card-bottom {
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;

        width: 100% !important;

        margin-top: 24px !important;

        padding-top: 19px !important;

        gap: 7px !important;

        border-top:
            1px solid
            #edf0ed !important;

        font-size: 10.5px !important;
        font-weight: 550 !important;

        color: #9ca3af !important;
    }

    #sdm-login-page .sdm-card-bottom svg {
        width: 13px !important;
        height: 13px !important;

        fill: none !important;
        stroke: currentColor !important;

        stroke-width: 2 !important;
        stroke-linecap: round !important;
        stroke-linejoin: round !important;
    }

    /* =========================================================
       EXTERNAL FOOTER
       ========================================================= */

    #sdm-login-page .sdm-page-footer {
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;

        margin-top: 18px !important;

        gap: 7px !important;

        font-size: 10.5px !important;
        font-weight: 500 !important;

        color:
            rgba(255, 255, 255, 0.64) !important;

        text-align: center !important;

        text-shadow:
            0 1px 5px
            rgba(0, 0, 0, 0.20) !important;
    }

    #sdm-login-page .sdm-page-footer-dot {
        width: 3px !important;
        height: 3px !important;

        border-radius: 50% !important;

        background:
            rgba(255, 255, 255, 0.48) !important;
    }

    /* =========================================================
       RESPONSIVE
       ========================================================= */

    @media (max-width: 520px) {
        #sdm-login-page {
            padding:
                22px
                15px !important;
        }

        #sdm-login-page .sdm-login-card {
            padding:
                0
                23px
                27px !important;

            border-radius: 20px !important;
        }

        #sdm-login-page .sdm-logo-section {
            padding-top: 28px !important;
        }

        #sdm-login-page .sdm-logo-container {
            width: 76px !important;
            height: 76px !important;

            border-radius: 19px !important;
        }

        #sdm-login-page .sdm-brand-name {
            font-size: 21px !important;
        }

        #sdm-login-page .sdm-login-header h1 {
            font-size: 22px !important;
        }
    }

    @media (max-height: 760px) {
        #sdm-login-page {
            align-items: flex-start !important;

            padding-top: 20px !important;
            padding-bottom: 20px !important;
        }

        #sdm-login-page .sdm-login-wrapper {
            margin-top: auto !important;
            margin-bottom: auto !important;
        }

        #sdm-login-page .sdm-login-card {
            padding:
                0
                34px
                25px !important;
        }

        #sdm-login-page .sdm-logo-section {
            padding-top: 24px !important;
        }

        #sdm-login-page .sdm-logo-container {
            width: 68px !important;
            height: 68px !important;

            margin-bottom: 10px !important;
        }

        #sdm-login-page .sdm-section-divider {
            margin:
                16px
                0
                17px !important;
        }

        #sdm-login-page .sdm-login-header {
            margin-bottom: 17px !important;
        }

        #sdm-login-page .sdm-login-form {
            gap: 14px !important;
        }

        #sdm-login-page .sdm-card-bottom {
            margin-top: 18px !important;
            padding-top: 15px !important;
        }

        #sdm-login-page .sdm-page-footer {
            margin-top: 12px !important;
        }
    }
`;

function UserIcon() {
    return (
        <svg
            viewBox="0 0 24 24"
            aria-hidden="true"
        >
            <path
                d="
                    M20 21
                    A8 8 0 0 0 4 21
                "
            />

            <circle
                cx="12"
                cy="7"
                r="4"
            />
        </svg>
    );
}

function LockIcon() {
    return (
        <svg
            viewBox="0 0 24 24"
            aria-hidden="true"
        >
            <rect
                x="4"
                y="10"
                width="16"
                height="11"
                rx="2"
            />

            <path
                d="
                    M8 10
                    V7
                    A4 4 0 0 1 16 7
                    V10
                "
            />
        </svg>
    );
}

function EyeIcon() {
    return (
        <svg
            viewBox="0 0 24 24"
            aria-hidden="true"
        >
            <path
                d="
                    M2.5 12
                    C4.5 7.8 7.8 5.5 12 5.5
                    C16.2 5.5 19.5 7.8 21.5 12
                    C19.5 16.2 16.2 18.5 12 18.5
                    C7.8 18.5 4.5 16.2 2.5 12
                "
            />

            <circle
                cx="12"
                cy="12"
                r="2.7"
            />
        </svg>
    );
}

function EyeOffIcon() {
    return (
        <svg
            viewBox="0 0 24 24"
            aria-hidden="true"
        >
            <path
                d="
                    M3 3
                    L21 21
                "
            />

            <path
                d="
                    M10.6 5.7
                    C11.1 5.57 11.57 5.5 12 5.5
                    C16.2 5.5 19.5 7.8 21.5 12
                    C20.85 13.37 20 14.55 19 15.5
                "
            />

            <path
                d="
                    M16.4 17.3
                    C15.1 18.1 13.6 18.5 12 18.5
                    C7.8 18.5 4.5 16.2 2.5 12
                    C3.35 10.2 4.5 8.75 5.9 7.7
                "
            />

            <path
                d="
                    M9.9 9.9
                    C9.5 10.4 9.3 11 9.3 12
                    C9.3 13.5 10.5 14.7 12 14.7
                    C13 14.7 13.6 14.5 14.1 14.1
                "
            />
        </svg>
    );
}

function AlertIcon() {
    return (
        <svg
            viewBox="0 0 24 24"
            aria-hidden="true"
        >
            <circle
                cx="12"
                cy="12"
                r="9"
            />

            <path
                d="
                    M12 8
                    V12
                "
            />

            <path
                d="
                    M12 16
                    H12.01
                "
            />
        </svg>
    );
}

function ArrowIcon() {
    return (
        <svg
            viewBox="0 0 24 24"
            aria-hidden="true"
        >
            <path
                d="
                    M5 12
                    H19
                "
            />

            <path
                d="
                    M13 6
                    L19 12
                    L13 18
                "
            />
        </svg>
    );
}

function ShieldIcon() {
    return (
        <svg
            viewBox="0 0 24 24"
            aria-hidden="true"
        >
            <path
                d="
                    M12 22
                    C12 22 20 18 20 12
                    V5
                    L12 2
                    L4 5
                    V12
                    C4 18 12 22 12 22
                "
            />

            <path
                d="
                    M9 12
                    L11 14
                    L15 10
                "
            />
        </svg>
    );
}

export default function LoginPage() {
    const navigate = useNavigate();

    const {
        login,
    } = useAuth();

    const [
        username,
        setUsername,
    ] = useState('');

    const [
        password,
        setPassword,
    ] = useState('');

    const [
        showPassword,
        setShowPassword,
    ] = useState(false);

    const [
        errorMessage,
        setErrorMessage,
    ] = useState('');

    const [
        isSubmitting,
        setIsSubmitting,
    ] = useState(false);

    const handleSubmit =
        async (
            event: FormEvent<HTMLFormElement>,
        ): Promise<void> => {
            event.preventDefault();

            if (isSubmitting) {
                return;
            }

            setErrorMessage('');

            setIsSubmitting(true);

            try {
                const user = await login({
                    username,
                    password,
                });

                navigate(
                    getRoleHome(user.role),
                    {
                        replace: true,
                    },
                );
            } catch (error) {
                if (error instanceof ApiError) {
                    const usernameError =
                        error.errors
                            ?.username
                        ?.[0];

                    setErrorMessage(
                        usernameError ??
                        error.message,
                    );
                } else {
                    setErrorMessage(
                        'An unexpected error occurred while logging in.',
                    );
                }
            } finally {
                setIsSubmitting(false);
            }
        };

    return (
        <main id="sdm-login-page">
            <style>
                {loginPageStyles}
            </style>

            <div
                className="sdm-background"
                aria-hidden="true"
            >
                <img
                    className="sdm-background-image"
                    src={sdmLoginImage}
                    alt=""
                />

                <div className="sdm-background-overlay" />

                <div className="sdm-background-vignette" />

                <div className="sdm-background-glow" />
            </div>

            <div className="sdm-login-wrapper">
                <section className="sdm-login-card">
                    <div
                        className="sdm-card-top-line"
                        aria-hidden="true"
                    />

                    <div className="sdm-logo-section">
                        <div className="sdm-logo-container">
                            <img
                                className="sdm-logo-image"
                                src={sdmLogo}
                                alt="SDM Agro POS"
                            />
                        </div>

                        <strong className="sdm-brand-name">
                            SDM Agro POS
                        </strong>

                        {/* <span className="sdm-brand-description">
                            Agricultural Retail Management System
                        </span> */}
                    </div>

                    {/* <div className="sdm-section-divider">
                        <span className="sdm-secure-badge">
                            <span
                                className="sdm-secure-dot"
                                aria-hidden="true"
                            />

                            Secure Staff Access
                        </span>
                    </div> */}

                    {/* <header className="sdm-login-header">
                        <h1>
                            Welcome back
                        </h1>

                        <p>
                            Sign in with your authorized account
                            to access the management system.
                        </p>
                    </header> */}

                    {errorMessage && (
                        <div
                            className="sdm-login-alert"
                            role="alert"
                        >
                            <span
                                className="sdm-alert-icon"
                                aria-hidden="true"
                            >
                                <AlertIcon />
                            </span>

                            <span>
                                {errorMessage}
                            </span>
                        </div>
                    )}

                    <form
                        className="sdm-login-form"
                        onSubmit={(event) => {
                            void handleSubmit(event);
                        }}
                    >
                        <label className="sdm-field">
                            <span className="sdm-field-label">
                                Username
                            </span>

                            <div className="sdm-input-wrapper">
                                <span
                                    className="sdm-input-icon"
                                    aria-hidden="true"
                                >
                                    <UserIcon />
                                </span>

                                <input
                                    className="sdm-input"
                                    type="text"
                                    value={username}
                                    autoComplete="username"
                                    placeholder="Enter your username"
                                    disabled={isSubmitting}
                                    autoFocus
                                    onChange={(event) => {
                                        setUsername(
                                            event.target.value,
                                        );
                                    }}
                                    required
                                />
                            </div>
                        </label>

                        <label className="sdm-field">
                            <span className="sdm-field-label">
                                Password
                            </span>

                            <div className="sdm-input-wrapper">
                                <span
                                    className="sdm-input-icon"
                                    aria-hidden="true"
                                >
                                    <LockIcon />
                                </span>

                                <input
                                    className="
                                        sdm-input
                                        sdm-password-input
                                    "
                                    type={
                                        showPassword
                                            ? 'text'
                                            : 'password'
                                    }
                                    value={password}
                                    autoComplete="current-password"
                                    placeholder="Enter your password"
                                    disabled={isSubmitting}
                                    onChange={(event) => {
                                        setPassword(
                                            event.target.value,
                                        );
                                    }}
                                    required
                                />

                                <button
                                    type="button"
                                    className="sdm-password-toggle"
                                    aria-label={
                                        showPassword
                                            ? 'Hide password'
                                            : 'Show password'
                                    }
                                    title={
                                        showPassword
                                            ? 'Hide password'
                                            : 'Show password'
                                    }
                                    disabled={isSubmitting}
                                    onClick={() => {
                                        setShowPassword(
                                            (
                                                currentValue,
                                            ) =>
                                                !currentValue,
                                        );
                                    }}
                                >
                                    {showPassword
                                        ? (
                                            <EyeOffIcon />
                                        )
                                        : (
                                            <EyeIcon />
                                        )}
                                </button>
                            </div>
                        </label>

                        <button
                            type="submit"
                            className="sdm-login-button"
                            disabled={isSubmitting}
                        >
                            <span className="sdm-button-content">
                                {isSubmitting
                                    ? (
                                        <>
                                            <span
                                                className="sdm-spinner"
                                                aria-hidden="true"
                                            />

                                            <span>
                                                Signing in...
                                            </span>
                                        </>
                                    )
                                    : (
                                        <>
                                            <span>
                                                Sign In
                                            </span>

                                            <span
                                                className="sdm-button-arrow"
                                                aria-hidden="true"
                                            >
                                                <ArrowIcon />
                                            </span>
                                        </>
                                    )}
                            </span>
                        </button>
                    </form>

                    <div className="sdm-card-bottom">
                        <ShieldIcon />

                        <span>
                            Protected access for authorized staff only
                        </span>
                    </div>
                </section>

                <div className="sdm-page-footer">
                    <span>
                        SDM Agro POS
                    </span>

                    <span
                        className="sdm-page-footer-dot"
                        aria-hidden="true"
                    />

                    <span>
                        Smart Agricultural Retail Management
                    </span>
                </div>
            </div>
        </main>
    );
}