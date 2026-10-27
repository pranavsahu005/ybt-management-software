<?php
/**
 * YBT Management Software — System & Business Settings
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/services/backup-service.php';
require_once __DIR__ . '/services/restore-service.php';
require_once __DIR__ . '/services/ai-provider-service.php';

Auth::requireLogin();
Permissions::require('settings.access');

$businessId = Auth::businessId();
$userId = Auth::id();
$business = Database::fetch("SELECT * FROM businesses WHERE id = ?", [$businessId]);

// Handle Business Profile Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_business') {
    Permissions::require('settings.edit');
    if (!Csrf::validate($_POST['_csrf_token'] ?? '')) {
        Flash::error('Invalid security token.');
    } else {
        Database::update('businesses', [
            'name' => trim($_POST['name'] ?? ''),
            'legal_name' => trim($_POST['legal_name'] ?? ''),
            'tagline' => trim($_POST['tagline'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'gstin' => strtoupper(trim($_POST['gstin'] ?? '')),
            'pan' => strtoupper(trim($_POST['pan'] ?? '')),
            'address_line1' => trim($_POST['address_line1'] ?? ''),
            'address_line2' => trim($_POST['address_line2'] ?? ''),
            'city' => trim($_POST['city'] ?? ''),
            'state' => trim($_POST['state'] ?? ''),
            'postal_code' => trim($_POST['postal_code'] ?? ''),
            'bank_name' => trim($_POST['bank_name'] ?? ''),
            'account_number' => trim($_POST['account_number'] ?? ''),
            'ifsc_code' => strtoupper(trim($_POST['ifsc_code'] ?? '')),
            'upi_id' => trim($_POST['upi_id'] ?? ''),
            'invoice_prefix' => trim($_POST['invoice_prefix'] ?? 'INV-'),
            'quotation_prefix' => trim($_POST['quotation_prefix'] ?? 'QT-'),
            'default_due_days' => max(1, (int)($_POST['default_due_days'] ?? 15)),
            'default_terms' => trim($_POST['default_terms'] ?? ''),
            'default_notes' => trim($_POST['default_notes'] ?? '')
        ], 'id = ?', [$businessId]);

        $_SESSION['business_name'] = trim($_POST['name'] ?? '');
        ActivityLog::log($businessId, $userId, 'settings_updated', 'business', $businessId, "Updated business profile & settings");
        Flash::success('Business settings saved successfully.');
        header('Location: ' . url('settings.php#business'));
        exit;
    }
}

// Handle AI Provider Configuration Save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_ai_provider') {
    Permissions::require('settings.edit');
    if (!Csrf::validate($_POST['_csrf_token'] ?? '')) {
        Flash::error('Invalid security token.');
    } else {
        try {
            $provId = AIProviderService::save($businessId, $_POST);
            ActivityLog::log($businessId, $userId, 'ai_provider_configured', 'ai_provider', $provId, "Configured AI Provider: " . ($_POST['display_name'] ?? 'AI Provider'));
            Flash::success('AI Provider configuration saved successfully.');
        } catch (Exception $e) {
            Flash::error('Failed to save AI Provider: ' . $e->getMessage());
        }
        header('Location: ' . url('settings.php#section-ai-providers'));
        exit;
    }
}

// Handle AI Provider Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_ai_provider') {
    Permissions::require('settings.edit');
    if (Csrf::validate($_POST['_csrf_token'] ?? '')) {
        $delId = (int)($_POST['provider_id'] ?? 0);
        AIProviderService::delete($delId, $businessId);
        ActivityLog::log($businessId, $userId, 'ai_provider_deleted', 'ai_provider', $delId, "Deleted AI Provider #{$delId}");
        Flash::success('AI Provider configuration removed.');
        header('Location: ' . url('settings.php#section-ai-providers'));
        exit;
    }
}

// Handle Set Default AI Provider
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'set_default_ai_provider') {
    Permissions::require('settings.edit');
    if (Csrf::validate($_POST['_csrf_token'] ?? '')) {
        $defId = (int)($_POST['provider_id'] ?? 0);
        Database::query("UPDATE ai_provider_configs SET is_default = 0 WHERE business_id = ?", [$businessId]);
        Database::query("UPDATE ai_provider_configs SET is_default = 1, is_active = 1 WHERE id = ? AND business_id = ?", [$defId, $businessId]);
        Flash::success('Default active AI Provider updated.');
        header('Location: ' . url('settings.php#section-ai-providers'));
        exit;
    }
}

// Handle Backup Generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_backup') {
    Permissions::requireOwner();
    if (Csrf::validate($_POST['_csrf_token'] ?? '')) {
        try {
            $backup = BackupService::createBackup($businessId, $userId);
            Flash::success("Backup created successfully: " . $backup['filename']);
            header('Location: ' . url('settings.php#backup'));
            exit;
        } catch (Exception $e) {
            Flash::error('Backup failed: ' . $e->getMessage());
        }
    }
}

// Handle Reset Demo Data
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset_demo') {
    Permissions::requireOwner();
    if (Csrf::validate($_POST['_csrf_token'] ?? '')) {
        $res = RestoreService::resetDemoData($businessId, $userId);
        if ($res['success']) {
            Flash::success('Demo data reset to initial baseline successfully.');
        } else {
            Flash::error('Failed to reset demo data: ' . $res['error']);
        }
        header('Location: ' . url('settings.php#backup'));
        exit;
    }
}

// Fetch existing backups
$backups = BackupService::listBackups($businessId);

// Fetch AI Provider data
$aiPresets = AIProviderService::getPresets();
$aiProviders = AIProviderService::getAll($businessId);
$activeAiProvider = AIProviderService::getActive($businessId);

$editProvider = null;
if (isset($_GET['edit_provider'])) {
    $editId = (int)$_GET['edit_provider'];
    $editProvider = Database::fetch("SELECT * FROM ai_provider_configs WHERE id = ? AND business_id = ?", [$editId, $businessId]);
    if ($editProvider && !empty($editProvider['api_key_encrypted'])) {
        $editProvider['api_key'] = decryptString($editProvider['api_key_encrypted']);
    }
}

$pageTitle = 'Business Settings';
$pageScripts = ['settings.js'];
require YBT_ROOT . '/includes/header.php';
?>

<div class="page-header mb-4">
    <h1 class="page-title">Business & Application Settings</h1>
    <p class="page-subtitle">Configure organization profile, tax rules, banking details, invoices, AI providers, and system backups</p>
</div>

<!-- Tabs Nav -->
<div class="tabs-nav mb-4">
    <a href="#business" class="tab-link settings-tab-btn active" data-target="#section-business">
        <i class="ti ti-building mr-1"></i> Business Profile
    </a>
    <a href="#banking" class="tab-link settings-tab-btn" data-target="#section-banking">
        <i class="ti ti-building-bank mr-1"></i> Banking & UPI
    </a>
    <a href="#invoicing" class="tab-link settings-tab-btn" data-target="#section-invoicing">
        <i class="ti ti-receipt mr-1"></i> Invoice Defaults
    </a>
    <a href="#ai-providers" class="tab-link settings-tab-btn" data-target="#section-ai-providers">
        <i class="ti ti-sparkles mr-1 text-primary"></i> AI Digital Manager & APIs
    </a>
    <?php if (Auth::isOwner()): ?>
    <a href="#backup" class="tab-link settings-tab-btn" data-target="#section-backup">
        <i class="ti ti-database mr-1"></i> Backup & Reset
    </a>
    <?php endif; ?>
</div>

<form method="POST" action="<?= url('settings.php') ?>" id="settings-form">
    <?= Csrf::field() ?>
    <input type="hidden" name="action" value="update_business">

    <!-- Section 1: Business Profile -->
    <div class="settings-section active" id="section-business">
        <div class="card mb-4">
            <div class="card-header">
                <h3 class="card-title">Company Identity & Tax Details</h3>
            </div>
            <div class="card-body">
                <div class="grid grid-cols-2 gap-3 mb-3">
                    <div class="form-group">
                        <label class="form-label required">Business Trade Name</label>
                        <input type="text" name="name" class="form-input" value="<?= e($business['name']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Legal Registered Entity Name</label>
                        <input type="text" name="legal_name" class="form-input" value="<?= e($business['legal_name']) ?>">
                    </div>
                </div>

                <div class="form-group mb-3">
                    <label class="form-label">Tagline</label>
                    <input type="text" name="tagline" class="form-input" value="<?= e($business['tagline']) ?>">
                </div>

                <div class="grid grid-cols-2 gap-3 mb-3">
                    <div class="form-group">
                        <label class="form-label">Official Email</label>
                        <input type="email" name="email" class="form-input" value="<?= e($business['email']) ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Official Phone</label>
                        <input type="text" name="phone" class="form-input" value="<?= e($business['phone']) ?>">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3 mb-3">
                    <div class="form-group">
                        <label class="form-label">GSTIN (Goods and Services Tax Number)</label>
                        <input type="text" name="gstin" class="form-input" value="<?= e($business['gstin']) ?>" maxlength="15">
                    </div>
                    <div class="form-group">
                        <label class="form-label">PAN (Permanent Account Number)</label>
                        <input type="text" name="pan" class="form-input" value="<?= e($business['pan']) ?>" maxlength="10">
                    </div>
                </div>

                <div class="form-group mb-3">
                    <label class="form-label">Registered Office Address</label>
                    <input type="text" name="address_line1" class="form-input mb-2" placeholder="Street / Tower / Suite" value="<?= e($business['address_line1']) ?>">
                    <input type="text" name="address_line2" class="form-input mb-2" placeholder="Area / Landmark" value="<?= e($business['address_line2']) ?>">
                    <div class="grid grid-cols-3 gap-2">
                        <input type="text" name="city" class="form-input" placeholder="City" value="<?= e($business['city']) ?>">
                        <input type="text" name="state" class="form-input" placeholder="State" value="<?= e($business['state']) ?>">
                        <input type="text" name="postal_code" class="form-input" placeholder="PIN Code" value="<?= e($business['postal_code']) ?>">
                    </div>
                </div>

                <?php if (Permissions::has('settings.edit')): ?>
                <button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy mr-1"></i> Save Business Profile</button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Section 2: Banking & UPI -->
    <div class="settings-section" id="section-banking">
        <div class="card mb-4">
            <div class="card-header">
                <h3 class="card-title">Bank Accounts & Remittance Info</h3>
            </div>
            <div class="card-body">
                <div class="grid grid-cols-2 gap-3 mb-3">
                    <div class="form-group">
                        <label class="form-label">Bank Name</label>
                        <input type="text" name="bank_name" class="form-input" placeholder="e.g. HDFC Bank" value="<?= e($business['bank_name']) ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Bank Account Number</label>
                        <input type="text" name="account_number" class="form-input" placeholder="502000..." value="<?= e($business['account_number']) ?>">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3 mb-3">
                    <div class="form-group">
                        <label class="form-label">IFSC Code</label>
                        <input type="text" name="ifsc_code" class="form-input" placeholder="HDFC0000240" value="<?= e($business['ifsc_code']) ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">UPI ID / VPA</label>
                        <input type="text" name="upi_id" class="form-input" placeholder="business@bank" value="<?= e($business['upi_id']) ?>">
                    </div>
                </div>

                <?php if (Permissions::has('settings.edit')): ?>
                <button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy mr-1"></i> Save Banking Details</button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Section 3: Invoicing Defaults -->
    <div class="settings-section" id="section-invoicing">
        <div class="card mb-4">
            <div class="card-header">
                <h3 class="card-title">Invoice Numbering & Default Terms</h3>
            </div>
            <div class="card-body">
                <div class="grid grid-cols-3 gap-3 mb-3">
                    <div class="form-group">
                        <label class="form-label">Invoice Prefix</label>
                        <input type="text" name="invoice_prefix" class="form-input" value="<?= e($business['invoice_prefix']) ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Quotation Prefix</label>
                        <input type="text" name="quotation_prefix" class="form-input" value="<?= e($business['quotation_prefix']) ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Default Payment Due Days</label>
                        <input type="number" name="default_due_days" class="form-input" value="<?= e($business['default_due_days']) ?>" min="1">
                    </div>
                </div>

                <div class="form-group mb-3">
                    <label class="form-label">Default Invoice Terms & Conditions</label>
                    <textarea name="default_terms" class="form-input" rows="3"><?= e($business['default_terms']) ?></textarea>
                </div>

                <div class="form-group mb-3">
                    <label class="form-label">Default Customer Notes</label>
                    <textarea name="default_notes" class="form-input" rows="2"><?= e($business['default_notes']) ?></textarea>
                </div>

                <?php if (Permissions::has('settings.edit')): ?>
                <button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy mr-1"></i> Save Invoicing Preferences</button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</form>

<!-- Section: AI Provider & API Setup -->
<div class="settings-section" id="section-ai-providers">
    <!-- Active Status Overview Card -->
    <div class="card mb-4 card-glow-primary">
        <div class="card-header d-flex justify-between align-center">
            <div>
                <h3 class="card-title">
                    <i class="ti ti-sparkles mr-2 text-primary"></i> Current Active AI Engine
                </h3>
                <p class="text-xs text-muted mb-0">The AI Digital Manager uses this engine for natural language queries, financial insights, and action proposals.</p>
            </div>
            <div>
                <?php if ($activeAiProvider && (!empty($activeAiProvider['api_key']) || $activeAiProvider['provider_type'] === 'custom')): ?>
                    <span class="badge badge-success">
                        <i class="ti ti-circle-check mr-1"></i> <?= e($activeAiProvider['display_name']) ?> Active
                    </span>
                <?php else: ?>
                    <span class="badge badge-info">
                        <i class="ti ti-cpu mr-1"></i> Built-in Reasoning Active
                    </span>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-body">
            <?php if ($activeAiProvider && (!empty($activeAiProvider['api_key']) || $activeAiProvider['provider_type'] === 'custom')): ?>
                <div class="d-flex align-center gap-3">
                    <div class="avatar avatar-md bg-primary-light text-primary avatar-circle">
                        <i class="ti <?= e($aiPresets[$activeAiProvider['provider_type']]['icon'] ?? 'ti-cpu') ?> text-xl"></i>
                    </div>
                    <div class="flex-1">
                        <div class="d-flex align-center gap-2 mb-1">
                            <strong><?= e($activeAiProvider['display_name']) ?></strong>
                            <span class="badge badge-subtle"><?= e($activeAiProvider['provider_type']) ?></span>
                            <?php if ($activeAiProvider['is_default']): ?>
                                <span class="badge badge-primary">Primary Default</span>
                            <?php endif; ?>
                        </div>
                        <div class="text-xs text-muted">
                            Model: <code><?= e($activeAiProvider['model_id'] ?: 'Default') ?></code> &bull; 
                            Endpoint: <code><?= e(truncate($activeAiProvider['base_url'] ?: 'Standard', 45)) ?></code> &bull;
                            Temperature: <code><?= e($activeAiProvider['temperature']) ?></code>
                        </div>
                    </div>
                    <a href="<?= url('ai-assistant.php') ?>" class="btn btn-sm btn-primary">
                        <i class="ti ti-messages mr-1"></i> Launch AI Assistant
                    </a>
                </div>
            <?php else: ?>
                <div class="d-flex align-center gap-3">
                    <div class="avatar avatar-md bg-info-light text-info avatar-circle">
                        <i class="ti ti-cpu text-xl"></i>
                    </div>
                    <div>
                        <div class="font-semibold text-sm">Autonomous Local Business Logic Active</div>
                        <p class="text-xs text-muted mb-0">
                            The assistant is currently running in local offline mode using database-driven business logic.
                            To enable generative natural language conversations, connect your <strong>Google AI Studio</strong> or <strong>OpenRouter</strong> API key below.
                        </p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Provider Configuration Form -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-between align-center">
            <h3 class="card-title">
                <i class="ti ti-key mr-2 text-primary"></i> <?= $editProvider ? 'Edit AI Provider #' . $editProvider['id'] : 'Configure New AI Provider API' ?>
            </h3>
            <?php if ($editProvider): ?>
                <a href="<?= url('settings.php#section-ai-providers') ?>" class="btn btn-xs btn-ghost">
                    <i class="ti ti-plus mr-1"></i> Add New Provider Instead
                </a>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <form method="POST" action="<?= url('settings.php') ?>" id="ai-provider-form">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="save_ai_provider">
                <input type="hidden" name="id" id="ai-provider-id" value="<?= e($editProvider['id'] ?? 0) ?>">

                <div class="grid grid-cols-2 gap-3 mb-3">
                    <div class="form-group">
                        <label class="form-label required">Provider Platform</label>
                        <select name="provider_type" id="ai-provider-type" class="form-select" required>
                            <?php 
                            $currType = $editProvider['provider_type'] ?? 'google_gemini';
                            foreach ($aiPresets as $type => $info): 
                            ?>
                                <option value="<?= e($type) ?>" <?= $currType === $type ? 'selected' : '' ?>>
                                    <?= e($info['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="text-xs text-muted mt-1">Select your preferred AI service or unified proxy.</span>
                    </div>

                    <div class="form-group">
                        <label class="form-label required">Configuration Label</label>
                        <input type="text" name="display_name" id="ai-display-name" class="form-input" 
                               placeholder="e.g. Google Gemini 1.5 Flash" 
                               value="<?= e($editProvider['display_name'] ?? 'Google AI Studio (Gemini)') ?>" required>
                    </div>
                </div>

                <div class="form-group mb-3">
                    <div class="d-flex justify-between align-center mb-1">
                        <label class="form-label mb-0 required">API Key</label>
                        <a href="https://aistudio.google.com/app/apikey" target="_blank" rel="noopener noreferrer" 
                           id="ai-api-key-link" class="text-xs text-primary font-medium">
                            Get Google AI Studio API Key ↗
                        </a>
                    </div>
                    <div class="input-icon-wrapper" style="position: relative;">
                        <input type="password" name="api_key" id="ai-api-key" class="form-input" 
                               placeholder="<?= $editProvider && !empty($editProvider['api_key']) ? 'Leave blank to retain saved encrypted key' : 'Paste API Key here (e.g. AIzaSy... or sk-or-v1-...)' ?>" 
                               autocomplete="off" style="padding-right: 40px;">
                        <button type="button" id="btn-toggle-ai-key" class="btn btn-ghost btn-xs" 
                                style="position: absolute; right: 8px; top: 50%; transform: translateY(-50%);" title="Toggle visibility">
                            <i class="ti ti-eye"></i>
                        </button>
                    </div>
                    <span class="text-xs text-muted mt-1">
                        <i class="ti ti-lock mr-1"></i> API keys are encrypted at rest with AES-256-CBC and never logged.
                        <?php if ($editProvider && !empty($editProvider['api_key'])): ?>
                            <span class="text-success font-medium ml-1">&bull; Currently saved key: <code><?= e(substr($editProvider['api_key'], 0, 4) . '••••••••' . substr($editProvider['api_key'], -4)) ?></code></span>
                        <?php endif; ?>
                    </span>
                </div>

                <div class="grid grid-cols-2 gap-3 mb-3">
                    <div class="form-group">
                        <label class="form-label required">Model ID</label>
                        <input type="text" name="model_id" id="ai-model-id" class="form-input" 
                               placeholder="e.g. gemini-1.5-flash or google/gemini-flash-1.5" 
                               value="<?= e($editProvider['model_id'] ?? 'gemini-1.5-flash') ?>" data-auto="1" required>
                        <div class="mt-2">
                            <span class="text-xs text-muted mr-1">Quick Select:</span>
                            <div id="ai-model-chips" class="d-inline-flex flex-wrap gap-1 align-center">
                                <!-- Populated dynamically by settings.js -->
                                <button type="button" class="btn btn-xs btn-outline-secondary" onclick="document.getElementById('ai-model-id').value='gemini-1.5-flash'">gemini-1.5-flash</button>
                                <button type="button" class="btn btn-xs btn-outline-secondary" onclick="document.getElementById('ai-model-id').value='gemini-1.5-pro'">gemini-1.5-pro</button>
                                <button type="button" class="btn btn-xs btn-outline-secondary" onclick="document.getElementById('ai-model-id').value='gemini-2.0-flash-exp'">gemini-2.0-flash-exp</button>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Base API URL</label>
                        <input type="text" name="base_url" id="ai-base-url" class="form-input" 
                               placeholder="e.g. https://generativelanguage.googleapis.com/v1beta" 
                               value="<?= e($editProvider['base_url'] ?? 'https://generativelanguage.googleapis.com/v1beta') ?>" data-auto="1">
                        <span class="text-xs text-muted mt-1">Pre-filled with official vendor endpoint. Change for custom reverse proxies or local models.</span>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3 mb-4">
                    <div class="form-group">
                        <label class="form-label">Temperature: <span id="temp-display" class="font-bold">0.70</span></label>
                        <input type="range" name="temperature" class="form-range" min="0" max="1" step="0.05" 
                               value="<?= e($editProvider['temperature'] ?? 0.70) ?>" 
                               oninput="document.getElementById('temp-display').textContent=parseFloat(this.value).toFixed(2);">
                        <div class="d-flex justify-between text-xs text-muted mt-1">
                            <span>0.0 (Precise / Factual)</span>
                            <span>1.0 (Creative)</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Max Output Tokens</label>
                        <select name="max_tokens" class="form-select">
                            <option value="1024" <?= ($editProvider['max_tokens'] ?? 4096) == 1024 ? 'selected' : '' ?>>1,024 tokens (Short)</option>
                            <option value="2048" <?= ($editProvider['max_tokens'] ?? 4096) == 2048 ? 'selected' : '' ?>>2,048 tokens (Standard)</option>
                            <option value="4096" <?= ($editProvider['max_tokens'] ?? 4096) == 4096 ? 'selected' : '' ?>>4,096 tokens (Detailed, Recommended)</option>
                            <option value="8192" <?= ($editProvider['max_tokens'] ?? 4096) == 8192 ? 'selected' : '' ?>>8,192 tokens (Extended)</option>
                        </select>
                    </div>
                </div>

                <div class="d-flex gap-4 mb-4">
                    <label class="d-flex align-center gap-2 cursor-pointer">
                        <input type="checkbox" name="is_active" value="1" <?= (!isset($editProvider) || !empty($editProvider['is_active'])) ? 'checked' : '' ?>>
                        <span class="text-sm font-medium">Enable this AI provider for requests</span>
                    </label>

                    <label class="d-flex align-center gap-2 cursor-pointer">
                        <input type="checkbox" name="is_default" value="1" <?= (!isset($editProvider) || !empty($editProvider['is_default'])) ? 'checked' : '' ?>>
                        <span class="text-sm font-medium">Set as primary default AI provider</span>
                    </label>
                </div>

                <div class="d-flex justify-between align-center pt-3 border-top">
                    <button type="button" id="btn-test-ai-provider" class="btn btn-secondary">
                        <i class="ti ti-bolt mr-1 text-warning"></i> Test Connection
                    </button>

                    <div class="d-flex gap-2">
                        <?php if ($editProvider): ?>
                            <a href="<?= url('settings.php#section-ai-providers') ?>" class="btn btn-ghost">Cancel</a>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-primary">
                            <i class="ti ti-device-floppy mr-1"></i> <?= $editProvider ? 'Update Provider' : 'Save AI Provider' ?>
                        </button>
                    </div>
                </div>

                <!-- Test Connection Feedback Message Container -->
                <div id="ai-test-result" style="display: none;"></div>
            </form>
        </div>
    </div>

    <!-- Configured Providers Directory -->
    <div class="card mb-4">
        <div class="card-header">
            <h3 class="card-title">
                <i class="ti ti-list mr-2 text-primary"></i> Configured AI Providers
            </h3>
        </div>
        <div class="card-body p-0">
            <?php if (empty($aiProviders)): ?>
                <div class="p-4 text-center text-muted text-sm">
                    <i class="ti ti-info-circle text-xl d-block mb-1 text-muted"></i>
                    No external AI providers configured yet. Configure <strong>Google AI Studio</strong> or <strong>OpenRouter</strong> above to enable live model queries.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Provider</th>
                                <th>Model ID</th>
                                <th>Base URL</th>
                                <th>API Key Status</th>
                                <th>Status</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($aiProviders as $p): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-center gap-2">
                                        <i class="ti <?= e($aiPresets[$p['provider_type']]['icon'] ?? 'ti-cpu') ?> text-primary text-md"></i>
                                        <div>
                                            <strong><?= e($p['display_name']) ?></strong>
                                            <div class="text-xs text-muted"><?= e($p['provider_type']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><code><?= e($p['model_id'] ?: 'Default') ?></code></td>
                                <td><span class="text-xs text-muted"><?= e(truncate($p['base_url'] ?: 'Vendor default', 32)) ?></span></td>
                                <td>
                                    <?php if ($p['has_key']): ?>
                                        <span class="badge badge-success"><i class="ti ti-lock mr-1"></i> <?= e($p['masked_key']) ?></span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">No Key Saved</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($p['is_default']): ?>
                                        <span class="badge badge-primary"><i class="ti ti-star mr-1"></i> Default</span>
                                    <?php elseif ($p['is_active']): ?>
                                        <span class="badge badge-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-subtle">Disabled</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="d-inline-flex gap-1">
                                        <a href="<?= url('settings.php?edit_provider=' . $p['id'] . '#section-ai-providers') ?>" 
                                           class="btn btn-xs btn-ghost" title="Edit provider">
                                            <i class="ti ti-pencil"></i>
                                        </a>

                                        <?php if (!$p['is_default']): ?>
                                        <form method="POST" action="<?= url('settings.php') ?>" class="d-inline">
                                            <?= Csrf::field() ?>
                                            <input type="hidden" name="action" value="set_default_ai_provider">
                                            <input type="hidden" name="provider_id" value="<?= $p['id'] ?>">
                                            <button type="submit" class="btn btn-xs btn-ghost text-primary" title="Make Primary Default">
                                                <i class="ti ti-star"></i>
                                            </button>
                                        </form>
                                        <?php endif; ?>

                                        <form method="POST" action="<?= url('settings.php') ?>" class="d-inline" 
                                              onsubmit="return confirm('Are you sure you want to remove this provider configuration?');">
                                            <?= Csrf::field() ?>
                                            <input type="hidden" name="action" value="delete_ai_provider">
                                            <input type="hidden" name="provider_id" value="<?= $p['id'] ?>">
                                            <button type="submit" class="btn btn-xs btn-ghost text-danger" title="Delete provider">
                                                <i class="ti ti-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
window.AI_PRESETS = <?= json_encode($aiPresets) ?>;
</script>

<!-- Section 4: Backup, Restore & Reset (Owner only) -->
<?php if (Auth::isOwner()): ?>
<div class="settings-section" id="section-backup">
    <div class="card mb-4">
        <div class="card-header d-flex justify-between align-center">
            <div>
                <h3 class="card-title"><i class="ti ti-database mr-1 text-primary"></i> Data Backup & Safety Restores</h3>
                <p class="text-xs text-muted mb-0">Generate standalone SQL archives of all business records and ledgers.</p>
            </div>
            <form method="POST" action="<?= url('settings.php') ?>">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="create_backup">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="ti ti-download mr-1"></i> Create Backup Now
                </button>
            </form>
        </div>
        <div class="card-body p-0">
            <?php if (empty($backups)): ?>
                <div class="p-4 text-center text-muted text-sm">No backup files created yet. Click above to generate your first backup.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Backup Filename</th>
                                <th>Generated At</th>
                                <th>Size</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($backups as $b): ?>
                            <tr>
                                <td><code><?= e($b['filename']) ?></code></td>
                                <td><?= formatDate($b['created_at']) ?> <?= date('H:i', strtotime($b['created_at'])) ?></td>
                                <td><?= formatFileSize($b['size_bytes']) ?></td>
                                <td><span class="badge badge-success">Completed</span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Factory Reset Demo Data -->
    <div class="card border-danger">
        <div class="card-header">
            <h3 class="card-title text-danger"><i class="ti ti-alert-triangle mr-1"></i> Reset Demo Data</h3>
        </div>
        <div class="card-body">
            <p class="text-sm text-muted mb-3">
                This operation resets the demo customers, invoices, leads, products, and tasks to their baseline demonstration dataset.
                Existing custom credentials will revert to <code>password123</code>.
            </p>
            <form method="POST" action="<?= url('settings.php') ?>" onsubmit="return confirm('Are you sure you want to restore the demo factory state?');">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="reset_demo">
                <button type="submit" class="btn btn-danger btn-sm">
                    <i class="ti ti-refresh mr-1"></i> Reset to Factory Demo State
                </button>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require YBT_ROOT . '/includes/footer.php'; ?>
