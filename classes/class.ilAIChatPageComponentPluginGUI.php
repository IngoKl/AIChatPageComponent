<?php

/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 *
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *
 *********************************************************************/

/**
 * AI Chat Page Component GUI
 * @author            Ingo Kleiber <ingo.kleiber@uni-koeln.de>
 * @ilCtrl_isCalledBy ilAIChatPageComponentPluginGUI: ilPCPluggedGUI
 */
class ilAIChatPageComponentPluginGUI extends ilPageComponentPluginGUI
{
    protected ilLanguage $lng;
    protected ilCtrl $ctrl;
    protected ilGlobalTemplateInterface $tpl;
    protected $request;

    public function __construct()
    {
        global $DIC;

        parent::__construct();

        $this->lng = $DIC->language();
        $this->ctrl = $DIC->ctrl();
        $this->tpl = $DIC['tpl'];
        $this->request = $DIC->http()->request();
    }

    /**
     * Execute command
     */
    public function executeCommand() : void
    {
        $next_class = $this->ctrl->getNextClass();

        switch ($next_class) {
            default:
                // perform valid commands
                $cmd = $this->ctrl->getCmd();
                if (in_array($cmd, array("create", "save", "edit", "update", "cancel"))) {
                    $this->$cmd();
                } else {
                    $this->tpl->setOnScreenMessage("failure", $this->lng->txt("msg_invalid_cmd"), true);
                    $this->returnToParent();
                }
                break;
        }
    }

    /**
     * Create
     */
    public function insert() : void
    {
        $form = $this->initForm(true);
        $this->tpl->setContent($form->getHTML());
    }

    /**
     * Save new pc example element
     */
    public function create() : void
    {
        $form = $this->initForm(true);
        if ($this->saveForm($form, true)) {
            ;
        }
        {
            $this->tpl->setOnScreenMessage("success", $this->lng->txt("msg_obj_modified"), true);
            $this->returnToParent();
        }
        $form->setValuesByPost();
        $this->tpl->setContent($form->getHTML());
    }

    public function edit() : void
    {
        $form = $this->initForm();

        $this->tpl->setContent($form->getHTML());
    }

    public function update() : void
    {
        $form = $this->initForm(false);
        if ($this->saveForm($form, false)) {
            ;
        }
        {
            $this->tpl->setOnScreenMessage("success", $this->lng->txt("msg_obj_modified"), true);
            $this->returnToParent();
        }
        $form->setValuesByPost();
        $this->tpl->setContent($form->getHTML());
    }

    /**
     * Init editing form
     */
    protected function initForm(bool $a_create = false) : ilPropertyFormGUI
    {
        $form = new ilPropertyFormGUI();

        // Chat title
        $chat_title = new ilTextInputGUI('Chat Title', 'chat_title');
        $chat_title->setMaxLength(255);
        $chat_title->setSize(40);
        $chat_title->setRequired(true);
        $chat_title->setInfo('Title for this AI chat component');
        $form->addItem($chat_title);

        // Get AIChat defaults
        $defaults = $this->getAIChatDefaults();

        // System prompt
        $system_prompt = new ilTextAreaInputGUI('System Prompt', 'system_prompt');
        $system_prompt->setRows(8);
        $system_prompt->setCols(80);
        $system_prompt->setRequired(true);
        $system_prompt->setInfo('Define the AI assistant\'s role and behavior for this chat');
        $system_prompt->setValue($defaults['prompt']);
        $form->addItem($system_prompt);

        // AI Service Selection based on AIChat configuration
        $ai_service = new ilSelectInputGUI('AI Service', 'ai_service');
        $service_options = $this->getAvailableAIServices();
        $ai_service->setOptions($service_options);
        $ai_service->setInfo('Select the AI service to use for this chat (based on AIChat plugin configuration)');
        $form->addItem($ai_service);

        // Max messages in memory
        $max_memory = new ilNumberInputGUI('Max Memory Messages', 'max_memory');
        $max_memory->setSize(10);
        $max_memory->setMinValue(1);
        $max_memory->setMaxValue(50);
        $max_memory->setValue($defaults['max_memory_messages']);
        $max_memory->setInfo('Number of previous messages to keep in conversation context');
        $form->addItem($max_memory);

        // Character limit per message
        $char_limit = new ilNumberInputGUI('Character Limit', 'char_limit');
        $char_limit->setSize(10);
        $char_limit->setMinValue(100);
        $char_limit->setMaxValue(8000);
        $char_limit->setValue($defaults['characters_limit']);
        $char_limit->setInfo('Maximum characters allowed per user message');
        $form->addItem($char_limit);

        // Chat persistence
        $persistent = new ilCheckboxInputGUI('Persistent Chat', 'persistent');
        $persistent->setInfo('If enabled, chat messages will be saved and restored when users return. If disabled, each session starts fresh.');
        $persistent->setChecked(false);
        $form->addItem($persistent);

        // Disclaimer
        $disclaimer = new ilTextAreaInputGUI('Legal Disclaimer', 'disclaimer');
        $disclaimer->setRows(4);
        $disclaimer->setCols(80);
        $disclaimer->setInfo('Optional legal disclaimer shown to users');
        $disclaimer->setValue($defaults['disclaimer']);
        $form->addItem($disclaimer);

        // save and cancel commands
        if ($a_create) {
            $this->addCreationButton($form);
            $form->addCommandButton("cancel", $this->lng->txt("cancel"));
            $form->setTitle('Create AI Chat');
        } else {
            $prop = $this->getProperties();
            $chat_title->setValue($prop['chat_title'] ?? '');
            $system_prompt->setValue($prop['system_prompt'] ?? '');
            $ai_service->setValue($prop['ai_service'] ?? 'default');
            $max_memory->setValue($prop['max_memory'] ?? 10);
            $char_limit->setValue($prop['char_limit'] ?? 2000);
            $persistent->setChecked($prop['persistent'] ?? false);
            $disclaimer->setValue($prop['disclaimer'] ?? '');

            $form->addCommandButton("update", $this->lng->txt("save"));
            $form->addCommandButton("cancel", $this->lng->txt("cancel"));
            $form->setTitle('Edit AI Chat');
        }

        $form->setFormAction($this->ctrl->getFormAction($this));
        return $form;
    }

    protected function saveForm(ilPropertyFormGUI $form, bool $a_create) : bool
    {
        if ($form->checkInput()) {
            $properties = $this->getProperties();

            // Save AI chat configuration
            $properties['chat_title'] = $form->getInput('chat_title');
            $properties['system_prompt'] = $form->getInput('system_prompt');
            $properties['ai_service'] = $form->getInput('ai_service');
            $properties['max_memory'] = (int) $form->getInput('max_memory');
            $properties['char_limit'] = (int) $form->getInput('char_limit');
            $properties['persistent'] = (bool) $form->getInput('persistent');
            $properties['disclaimer'] = $form->getInput('disclaimer');

            // Generate unique chat ID if creating new
            if ($a_create) {
                $properties['chat_id'] = uniqid('chat_', true);
            }

            if ($a_create) {
                return $this->createElement($properties);
            } else {
                return $this->updateElement($properties);
            }
        }

        return false;
    }

    /**
     * Cancel
     */
    public function cancel()
    {
        $this->returnToParent();
    }

    /**
     * Get HTML for element
     * @param string    page mode (edit, presentation, print, preview, offline)
     * @return string   html code
     */
    public function getElementHTML(string $a_mode, array $a_properties, string $a_plugin_version) : string
    {
        // In edit mode, show placeholder
        if ($a_mode === 'edit') {
            return $this->renderEditPlaceholder($a_properties);
        }

        // Presentation mode: show full interface
        return $this->renderChatInterface($a_properties);
    }

    /**
     * Render edit mode placeholder
     */
    private function renderEditPlaceholder(array $properties) : string
    {
        $tpl = new ilTemplate(
            "tpl.ai_chat_placeholder.html", 
            true, 
            true, 
            $this->plugin->getDirectory()
        );
        
        $tpl->setVariable("CHAT_TITLE", htmlspecialchars($properties['chat_title'] ?? 'AI Chat'));
        $tpl->setVariable("PLACEHOLDER_TEXT", 'AI Chat Component (Click to edit configuration)');
        
        // Add CSS for edit mode
        $this->addChatAssets();
        
        return $tpl->get();
    }

    /**
     * Render chat interface for presentation mode
     */
    private function renderChatInterface(array $properties) : string
    {
        $tpl = new ilTemplate(
            "tpl.ai_chat.html", 
            true, 
            true, 
            $this->plugin->getDirectory()
        );
        
        // Get chat configuration
        $chat_id = $properties['chat_id'] ?? uniqid('chat_', true);
        $container_id = 'ai-chat-' . md5($chat_id);
        $messages_id = $container_id . '-messages';
        
        // Set basic template variables
        $tpl->setVariable("CONTAINER_ID", $container_id);
        $tpl->setVariable("MESSAGES_ID", $messages_id);
        $tpl->setVariable("CHAT_ID", htmlspecialchars($chat_id));
        $tpl->setVariable("CHAT_TITLE", htmlspecialchars($properties['chat_title'] ?? 'AI Chat'));
        $tpl->setVariable("WELCOME_MESSAGE", 'Start a conversation...');
        $tpl->setVariable("INPUT_PLACEHOLDER", 'Type your message...');
        $tpl->setVariable("SEND_BUTTON_TEXT", 'Send');
        $tpl->setVariable("LOADING_TEXT", 'Thinking...');
        $tpl->setVariable("CHAR_LIMIT", (int)($properties['char_limit'] ?? 2000));
        
        // Set data attributes for JavaScript configuration
        $tpl->setVariable("API_URL", htmlspecialchars($this->getAIChatApiUrl()));
        $tpl->setVariable("SYSTEM_PROMPT", htmlspecialchars($properties['system_prompt'] ?? 'You are a helpful AI assistant.'));
        $tpl->setVariable("MAX_MEMORY", (int)($properties['max_memory'] ?? 10));
        $tpl->setVariable("PERSISTENT", $properties['persistent'] ?? false ? 'true' : 'false');
        $tpl->setVariable("AI_SERVICE", htmlspecialchars($properties['ai_service'] ?? 'default'));
        
        // Handle optional disclaimer
        if (!empty($properties['disclaimer'])) {
            $tpl->setCurrentBlock("disclaimer");
            $tpl->setVariable("DISCLAIMER", htmlspecialchars($properties['disclaimer']));
            $tpl->parseCurrentBlock();
        }
        
        // Add CSS and JavaScript assets
        $this->addChatAssets();
        
        return $tpl->get();
    }

    /**
     * Add CSS and JavaScript assets for AI chat
     */
    private function addChatAssets() : void
    {
        global $DIC;
        $tpl = $DIC['tpl'];
        
        // Add CSS
        $tpl->addCss($this->plugin->getDirectory() . "/css/ai_chat.css");
        
        // Add JavaScript
        $tpl->addJavaScript($this->plugin->getDirectory() . "/js/ai_chat.js");
    }


    /**
     * Get the AIChatPageComponent API URL for AJAX requests
     */
    private function getAIChatApiUrl() : string
    {
        global $DIC;
        
        try {
            // Use the standalone API endpoint
            $plugin_dir = $this->plugin->getDirectory();
            $api_url = $plugin_dir . '/api.php';
            error_log('AIChatPageComponent: Using standalone API URL: ' . $api_url);
            return $api_url;
        } catch (Exception $e) {
            error_log('AIChatPageComponent: Failed to generate API URL: ' . $e->getMessage());
            return "";
        }
    }

    /**
     * Get available AI services from AIChat configuration
     */
    private function getAvailableAIServices() : array
    {
        $services = ['default' => 'Use Global Default'];
        
        try {
            // Include the config class
            require_once(__DIR__ . '/platform/class.AIChatPageComponentConfig.php');
            
            // Get available services from AIChat config
            $available_services = \platform\AIChatPageComponentConfig::get('available_services');
            
            if (is_array($available_services)) {
                if (isset($available_services['openai']) && $available_services['openai'] == "1") {
                    $services['openai'] = 'OpenAI GPT';
                }
                if (isset($available_services['ramses']) && $available_services['ramses'] == "1") {
                    $services['ramses'] = 'RAMSES';
                }
                if (isset($available_services['ollama']) && $available_services['ollama'] == "1") {
                    $services['ollama'] = 'Ollama';
                }
                if (isset($available_services['gwdg']) && $available_services['gwdg'] == "1") {
                    $services['gwdg'] = 'GWDG';
                }
            }
        } catch (Exception $e) {
            error_log('AIChatPageComponent: Failed to load available services from AIChat config: ' . $e->getMessage());
            // Fallback to basic options
            $services['openai'] = 'OpenAI GPT';
            $services['ramses'] = 'RAMSES';
        }
        
        return $services;
    }

    /**
     * Get default values from AIChat configuration
     */
    private function getAIChatDefaults() : array
    {
        $defaults = [
            'prompt' => 'You are a helpful AI assistant. Please provide accurate and helpful responses.',
            'characters_limit' => 2000,
            'max_memory_messages' => 10,
            'disclaimer' => ''
        ];
        
        try {
            require_once(__DIR__ . '/platform/class.AIChatPageComponentConfig.php');
            
            $prompt = \platform\AIChatPageComponentConfig::get('prompt');
            if (!empty($prompt)) {
                $defaults['prompt'] = $prompt;
            }
            
            $char_limit = \platform\AIChatPageComponentConfig::get('characters_limit');
            if (!empty($char_limit)) {
                $defaults['characters_limit'] = (int)$char_limit;
            }
            
            $max_memory = \platform\AIChatPageComponentConfig::get('max_memory_messages');
            if (!empty($max_memory)) {
                $defaults['max_memory_messages'] = (int)$max_memory;
            }
            
            $disclaimer = \platform\AIChatPageComponentConfig::get('disclaimer');
            if (!empty($disclaimer)) {
                $defaults['disclaimer'] = $disclaimer;
            }
        } catch (Exception $e) {
            error_log('AIChatPageComponent: Failed to load defaults from AIChat config: ' . $e->getMessage());
        }
        
        return $defaults;
    }

    /**
     * Get information about the page that embeds the component
     * @return    array    key => value
     */
    public function getPageInfo() : array
    {
        return array(
            'page_id' => $this->plugin->getPageId(),
            'parent_id' => $this->plugin->getParentId(),
            'parent_type' => $this->plugin->getParentType()
        );
    }


}