/**
 * YBT Management Software — Settings Page JS
 * Tab switching, color presets, and backup handling
 */
(function() {
    'use strict';

    const SettingsPage = {
        init() {
            this.initTabs();
            this.initColorPickers();
            this.initAiProviders();
        },

        initTabs() {
            const tabs = document.querySelectorAll('.settings-tab-btn');
            const sections = document.querySelectorAll('.settings-section');

            tabs.forEach(tab => {
                tab.addEventListener('click', (e) => {
                    e.preventDefault();
                    const target = tab.getAttribute('data-target');

                    tabs.forEach(t => t.classList.remove('active'));
                    sections.forEach(s => s.classList.remove('active'));

                    tab.classList.add('active');
                    const targetEl = document.querySelector(target);
                    if (targetEl) targetEl.classList.add('active');

                    // Save active tab in URL hash
                    window.location.hash = target;
                });
            });

            // Activate tab from URL hash if present
            if (window.location.hash) {
                const activeTab = document.querySelector(`.settings-tab-btn[data-target="${window.location.hash}"]`);
                if (activeTab) activeTab.click();
            }
        },

        initColorPickers() {
            document.querySelectorAll('[data-color-preset]').forEach(btn => {
                btn.addEventListener('click', () => {
                    const color = btn.getAttribute('data-color-preset');
                    const input = document.querySelector('#primary-color-input');
                    if (input) {
                        input.value = color;
                        input.dispatchEvent(new Event('input'));
                    }
                });
            });
        },

        initAiProviders() {
            const providerSelect = document.getElementById('ai-provider-type');
            const baseUrlInput = document.getElementById('ai-base-url');
            const modelInput = document.getElementById('ai-model-id');
            const apiKeyHelpLink = document.getElementById('ai-api-key-link');
            const modelChipsContainer = document.getElementById('ai-model-chips');
            const testBtn = document.getElementById('btn-test-ai-provider');
            const testResult = document.getElementById('ai-test-result');
            const toggleKeyBtn = document.getElementById('btn-toggle-ai-key');
            const apiKeyInput = document.getElementById('ai-api-key');

            if (!providerSelect) return;

            const presets = window.AI_PRESETS || {};

            const updateFieldsForProvider = (pType) => {
                const info = presets[pType];
                if (!info) return;

                if (baseUrlInput && (!baseUrlInput.value || baseUrlInput.getAttribute('data-auto') === '1')) {
                    baseUrlInput.value = info.default_base_url;
                    baseUrlInput.setAttribute('data-auto', '1');
                }

                if (modelInput && (!modelInput.value || modelInput.getAttribute('data-auto') === '1')) {
                    modelInput.value = info.default_model;
                    modelInput.setAttribute('data-auto', '1');
                }

                if (apiKeyHelpLink) {
                    apiKeyHelpLink.href = info.docs_url;
                    apiKeyHelpLink.textContent = `Get ${info.name} API Key ↗`;
                }

                if (modelChipsContainer && info.suggested_models) {
                    modelChipsContainer.innerHTML = '';
                    Object.entries(info.suggested_models).forEach(([mId, label]) => {
                        const chip = document.createElement('button');
                        chip.type = 'button';
                        chip.className = 'btn btn-xs btn-outline-secondary';
                        chip.textContent = mId;
                        chip.title = label;
                        chip.addEventListener('click', (e) => {
                            e.preventDefault();
                            if (modelInput) {
                                modelInput.value = mId;
                                modelInput.removeAttribute('data-auto');
                            }
                        });
                        modelChipsContainer.appendChild(chip);
                    });
                }
            };

            providerSelect.addEventListener('change', () => {
                updateFieldsForProvider(providerSelect.value);
            });

            if (baseUrlInput) {
                baseUrlInput.addEventListener('input', () => {
                    baseUrlInput.removeAttribute('data-auto');
                });
            }

            if (modelInput) {
                modelInput.addEventListener('input', () => {
                    modelInput.removeAttribute('data-auto');
                });
            }

            if (toggleKeyBtn && apiKeyInput) {
                toggleKeyBtn.addEventListener('click', () => {
                    const isPassword = apiKeyInput.type === 'password';
                    apiKeyInput.type = isPassword ? 'text' : 'password';
                    const icon = toggleKeyBtn.querySelector('i');
                    if (icon) {
                        icon.className = isPassword ? 'ti ti-eye-off' : 'ti ti-eye';
                    }
                });
            }

            if (testBtn) {
                testBtn.addEventListener('click', async () => {
                    const pType = providerSelect.value;
                    const key = apiKeyInput ? apiKeyInput.value.trim() : '';
                    const model = modelInput ? modelInput.value.trim() : '';
                    const base = baseUrlInput ? baseUrlInput.value.trim() : '';
                    const pIdEl = document.getElementById('ai-provider-id');
                    const pId = pIdEl ? pIdEl.value : 0;

                    testBtn.disabled = true;
                    testBtn.innerHTML = '<i class="ti ti-loader mr-1"></i> Testing...';
                    if (testResult) {
                        testResult.style.display = 'block';
                        testResult.className = 'alert alert-info mt-3';
                        testResult.innerHTML = '<i class="ti ti-loader mr-1"></i> Connecting to ' + pType + ' endpoint...';
                    }

                    try {
                        const resp = await fetch(window.APP_URL + '/api/ai-test-provider.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                provider_type: pType,
                                api_key: key,
                                model_id: model,
                                base_url: base,
                                provider_id: pId
                            })
                        });
                        const data = await resp.json();

                        if (data.success) {
                            testResult.className = 'alert alert-success mt-3';
                            testResult.innerHTML = '<i class="ti ti-circle-check mr-1"></i> ' + data.message + ' (Latency: ' + data.latency_ms + 'ms)';
                        } else {
                            testResult.className = 'alert alert-danger mt-3';
                            testResult.innerHTML = '<i class="ti ti-alert-triangle mr-1"></i> ' + (data.error || 'Connection test failed.');
                        }
                    } catch (err) {
                        testResult.className = 'alert alert-danger mt-3';
                        testResult.innerHTML = '<i class="ti ti-alert-triangle mr-1"></i> Network error: ' + err.message;
                    } finally {
                        testBtn.disabled = false;
                        testBtn.innerHTML = '<i class="ti ti-bolt mr-1"></i> Test Connection';
                    }
                });
            }
        }
    };

    window.SettingsPage = SettingsPage;
    document.addEventListener('DOMContentLoaded', () => SettingsPage.init());
})();
