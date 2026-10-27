/**
 * YBT Management Software — AI Assistant Chat
 * Interactive natural language query interface & action proposal approvals
 */
(function() {
    'use strict';

    const AIChat = {
        init() {
            this.container = document.querySelector('#ai-messages-container');
            this.input = document.querySelector('#ai-chat-input');
            this.sendBtn = document.querySelector('#ai-send-btn');
            this.form = document.querySelector('#ai-chat-form');

            if (!this.container || !this.input) return;

            this.bindEvents();
        },

        bindEvents() {
            if (this.form) {
                this.form.addEventListener('submit', (e) => {
                    e.preventDefault();
                    this.sendMessage();
                });
            }

            // Quick suggestion chips
            document.querySelectorAll('.ai-prompt-chip').forEach(chip => {
                chip.addEventListener('click', () => {
                    this.input.value = chip.textContent.trim();
                    this.sendMessage();
                });
            });

            // Action proposal approval & rejection handlers
            this.container.addEventListener('click', (e) => {
                const approveBtn = e.target.closest('[data-proposal-action="approve"]');
                const rejectBtn = e.target.closest('[data-proposal-action="reject"]');

                if (approveBtn) {
                    const proposalId = approveBtn.getAttribute('data-proposal-id');
                    this.handleProposal(proposalId, 'approve', approveBtn.closest('.ai-proposal-card'));
                } else if (rejectBtn) {
                    const proposalId = rejectBtn.getAttribute('data-proposal-id');
                    this.handleProposal(proposalId, 'reject', rejectBtn.closest('.ai-proposal-card'));
                }
            });
        },

        sendMessage() {
            const text = this.input.value.trim();
            if (!text) return;

            // Render user bubble
            this.appendMessage('user', text);
            this.input.value = '';
            this.input.focus();

            // Typing indicator
            const typingEl = this.appendTypingIndicator();
            this.scrollToBottom();

            // Fetch from backend
            fetch(`${window.APP_URL || ''}/api/ai.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': window.CSRF_TOKEN || ''
                },
                body: JSON.stringify({ message: text })
            })
            .then(res => res.json())
            .then(data => {
                typingEl.remove();
                if (data.success) {
                    this.appendMessage('assistant', data.response, data.proposal, data.chart);
                } else {
                    this.appendMessage('assistant', `⚠️ ${data.error || 'Unable to process query.'}`);
                }
                this.scrollToBottom();
            })
            .catch(err => {
                typingEl.remove();
                this.appendMessage('assistant', '⚠️ Connection error. Please verify server connection and try again.');
                this.scrollToBottom();
            });
        },

        appendMessage(role, text, proposal = null, chart = null) {
            const msgEl = document.createElement('div');
            msgEl.className = `ai-message ai-message-${role}`;

            const avatar = role === 'user'
                ? `<div class="ai-avatar user-avatar"><i class="ti ti-user"></i></div>`
                : `<div class="ai-avatar bot-avatar"><i class="ti ti-sparkles"></i></div>`;

            let contentHtml = `<div class="ai-bubble">${this.formatText(text)}</div>`;

            // If an action proposal is attached
            if (proposal) {
                contentHtml += `
                    <div class="ai-proposal-card mt-2">
                        <div class="ai-proposal-header">
                            <i class="ti ti-shield-alert text-warning"></i>
                            <strong>Action Proposal: ${proposal.title}</strong>
                            <span class="badge badge-warning">Requires Owner Approval</span>
                        </div>
                        <div class="ai-proposal-body mt-1">
                            <p class="text-sm">${proposal.description}</p>
                            ${proposal.preview ? `<div class="proposal-preview-box">${proposal.preview}</div>` : ''}
                        </div>
                        <div class="ai-proposal-actions mt-2">
                            <button type="button" class="btn btn-sm btn-primary" data-proposal-action="approve" data-proposal-id="${proposal.id}">
                                <i class="ti ti-check"></i> Approve & Execute
                            </button>
                            <button type="button" class="btn btn-sm btn-ghost text-danger" data-proposal-action="reject" data-proposal-id="${proposal.id}">
                                <i class="ti ti-x"></i> Reject
                            </button>
                        </div>
                    </div>
                `;
            }

            msgEl.innerHTML = `
                ${avatar}
                <div class="ai-message-content">
                    ${contentHtml}
                    <div class="ai-message-time">${new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</div>
                </div>
            `;

            this.container.appendChild(msgEl);
            return msgEl;
        },

        appendTypingIndicator() {
            const typingEl = document.createElement('div');
            typingEl.className = 'ai-message ai-message-assistant ai-typing';
            typingEl.innerHTML = `
                <div class="ai-avatar bot-avatar"><i class="ti ti-sparkles"></i></div>
                <div class="ai-bubble">
                    <span class="typing-dot"></span>
                    <span class="typing-dot"></span>
                    <span class="typing-dot"></span>
                </div>
            `;
            this.container.appendChild(typingEl);
            return typingEl;
        },

        handleProposal(proposalId, action, cardEl) {
            fetch(`${window.APP_URL || ''}/api/ai-action.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': window.CSRF_TOKEN || ''
                },
                body: JSON.stringify({ proposal_id: proposalId, action: action })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    cardEl.innerHTML = `
                        <div class="alert alert-${action === 'approve' ? 'success' : 'secondary'}">
                            <i class="ti ${action === 'approve' ? 'ti-check' : 'ti-ban'}"></i>
                            ${action === 'approve' ? 'Action approved and executed successfully!' : 'Action rejected.'}
                        </div>
                    `;
                    if (window.App) App.toast(action === 'approve' ? 'Action executed' : 'Action rejected', 'info');
                } else {
                    if (window.App) App.toast(data.error || 'Failed to process proposal', 'error');
                }
            })
            .catch(() => {
                if (window.App) App.toast('Network error while processing proposal', 'error');
            });
        },

        formatText(text) {
            // Safe basic formatting for line breaks, bold, code snippets
            let safe = text
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');

            // Bold **text**
            safe = safe.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
            // Bullet points
            safe = safe.replace(/^- (.*$)/gim, '• $1');
            // Line breaks
            safe = safe.replace(/\n/g, '<br>');

            return safe;
        },

        scrollToBottom() {
            this.container.scrollTop = this.container.scrollHeight;
        }
    };

    window.AIChat = AIChat;
    document.addEventListener('DOMContentLoaded', () => AIChat.init());
})();
