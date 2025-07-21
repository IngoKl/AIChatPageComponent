/**
 * AI Chat Page Component JavaScript
 */
class AIChatPageComponent {
    constructor(containerId) {
        this.containerId = containerId;
        this.container = document.getElementById(containerId);
        this.isLoading = false;
        this.messageHistory = [];
        
        if (!this.container) {
            console.error('AIChatPageComponent: Container not found with ID:', containerId);
            return;
        }
        
        this.init();
    }
    
    init() {
        this.messagesArea = this.container.querySelector('.ai-chat-messages');
        this.inputArea = this.container.querySelector('.ai-chat-input');
        this.sendButton = this.container.querySelector('.ai-chat-send');
        this.welcomeMsg = this.container.querySelector('.ai-chat-welcome');
        this.loadingDiv = this.container.querySelector('.ai-chat-loading');
        
        // Get configuration from data attributes
        this.chatId = this.container.dataset.chatId;
        this.apiUrl = this.container.dataset.apiUrl;
        this.systemPrompt = this.container.dataset.systemPrompt;
        this.maxMemory = parseInt(this.container.dataset.maxMemory) || 10;
        this.charLimit = parseInt(this.container.dataset.charLimit) || 2000;
        this.persistent = this.container.dataset.persistent === 'true';
        this.aiService = this.container.dataset.aiService || 'default';
        
        console.log('AIChatPageComponent: Initialized with config:', {
            chatId: this.chatId,
            apiUrl: this.apiUrl ? 'set' : 'not set',
            maxMemory: this.maxMemory,
            charLimit: this.charLimit,
            persistent: this.persistent,
            aiService: this.aiService
        });
        
        if (!this.sendButton || !this.inputArea) {
            console.error('AIChatPageComponent: Required elements not found');
            return;
        }
        
        this.bindEvents();
        this.loadChatHistory();
    }
    
    bindEvents() {
        // Send button click
        this.sendButton.addEventListener('click', (e) => {
            e.preventDefault();
            this.sendMessage();
        });
        
        // Enter key in textarea
        this.inputArea.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.sendMessage();
            }
        });
    }
    
    sendMessage() {
        if (this.isLoading) {
            console.log('AIChatPageComponent: Already loading, skipping');
            return;
        }
        
        const message = this.inputArea.value.trim();
        if (!message) {
            this.inputArea.focus();
            return;
        }
        
        if (message.length > this.charLimit) {
            alert(`Message too long. Maximum ${this.charLimit} characters allowed.`);
            return;
        }
        
        // Add user message to display
        this.addMessageToDisplay('user', message);
        this.inputArea.value = '';
        
        // Show loading
        this.setLoading(true);
        
        // Send message to AI
        this.sendMessageToAI(message);
    }
    
    async sendMessageToAI(message) {
        try {
            // Check if API is available
            if (!this.apiUrl || this.apiUrl === '') {
                throw new Error('API URL not configured. Please ensure the plugin is properly installed.');
            }
            
            console.log('AIChatPageComponent: Sending message to API:', message);
            
            const requestBody = new URLSearchParams({
                action: 'send_message',
                chat_id: this.chatId,
                message: message,
                system_prompt: this.systemPrompt,
                max_memory: this.maxMemory,
                persistent: this.persistent,
                ai_service: this.aiService
            });
            
            // Send the message to AIChatPageComponent API
            const response = await fetch(this.apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: requestBody
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const responseText = await response.text();
            
            let data;
            try {
                data = JSON.parse(responseText);
            } catch (e) {
                console.error('Failed to parse JSON:', responseText);
                throw new Error('Invalid JSON response: ' + responseText.substring(0, 100));
            }
            
            if (!data.success) {
                throw new Error(data.error || 'Unknown error occurred');
            }
            
            // Extract AI response from ILIAS API format
            const aiResponse = data.message;
            
            if (aiResponse) {
                this.addMessageToDisplay('assistant', aiResponse);
            } else {
                console.error('AIChatPageComponent: Unexpected response structure:', data);
                throw new Error('No AI response received');
            }
            
            this.setLoading(false);
            this.saveChatHistory();
            
        } catch (error) {
            console.error('AIChatPageComponent: Error:', error);
            this.setLoading(false);
            console.log('AIChatPageComponent: API URL:', this.apiUrl);
            this.addMessageToDisplay('system', 'Error: ' + error.message + '. Please ensure the AIChat plugin is properly configured.');
        }
    }
    
    
    addMessageToDisplay(role, content) {
        this.displayMessageOnly(role, content);
        
        // Add to history
        this.messageHistory.push({
            role: role,
            content: content,
            timestamp: Date.now()
        });
        
        // Limit history size
        if (this.messageHistory.length > this.maxMemory * 2) {
            this.messageHistory = this.messageHistory.slice(-this.maxMemory * 2);
        }
    }
    
    displayMessageOnly(role, content) {
        if (this.welcomeMsg && this.welcomeMsg.parentNode) {
            this.welcomeMsg.remove();
        }
        
        const messageDiv = document.createElement('div');
        messageDiv.className = 'ai-chat-message ' + role;
        messageDiv.textContent = content;
        this.messagesArea.appendChild(messageDiv);
        this.messagesArea.scrollTop = this.messagesArea.scrollHeight;
    }
    
    setLoading(loading) {
        this.isLoading = loading;
        this.sendButton.disabled = loading;
        this.sendButton.textContent = loading ? 'Sending...' : 'Send';
        
        if (this.loadingDiv) {
            this.loadingDiv.style.display = loading ? 'block' : 'none';
        }
    }
    
    saveChatHistory() {
        localStorage.setItem(`ai_chat_${this.chatId}`, JSON.stringify(this.messageHistory));
    }
    
    async loadChatHistory() {
        if (this.persistent) {
            // Load from server for persistent chats
            try {
                const response = await fetch(this.apiUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: new URLSearchParams({
                        action: 'load_chat',
                        chat_id: this.chatId,
                        persistent: this.persistent
                    })
                });
                
                if (response.ok) {
                    const data = await response.json();
                    if (data.success && data.chat && data.chat.messages) {
                        this.messageHistory = data.chat.messages.map(msg => ({
                            role: msg.role,
                            content: msg.content || msg.message,
                            timestamp: msg.timestamp
                        }));
                        
                        this.messageHistory.forEach(msg => {
                            if (msg.role !== 'system') {
                                this.displayMessageOnly(msg.role, msg.content);
                            }
                        });
                    }
                }
            } catch (e) {
                console.error('Failed to load persistent chat history:', e);
                // Fall back to local storage
                this.loadLocalChatHistory();
            }
        } else {
            // Load from local storage for non-persistent chats
            this.loadLocalChatHistory();
        }
    }
    
    loadLocalChatHistory() {
        const saved = localStorage.getItem(`ai_chat_${this.chatId}`);
        if (saved) {
            try {
                this.messageHistory = JSON.parse(saved);
                this.messageHistory.forEach(msg => {
                    if (msg.role !== 'system') {
                        this.displayMessageOnly(msg.role, msg.content);
                    }
                });
            } catch (e) {
                console.error('Failed to load local chat history:', e);
            }
        }
    }
}

// Initialize AI Chat components when DOM is ready
function initAIChatComponents() {
    console.log('AIChatPageComponent: Initializing components...');
    
    const containers = document.querySelectorAll('.ai-chat-container');
    containers.forEach(container => {
        if (container.id) {
            console.log('AIChatPageComponent: Initializing container:', container.id);
            new AIChatPageComponent(container.id);
        }
    });
}

// Auto-initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAIChatComponents);
} else {
    // DOM is already loaded
    setTimeout(initAIChatComponents, 100);
}