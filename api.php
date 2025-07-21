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
 * Standalone API endpoint for AIChatPageComponent
 * This file handles AJAX requests from the frontend
 */

// Initialize ILIAS
$ilias_root = dirname(dirname(dirname(dirname(dirname(dirname(dirname(__DIR__)))))));
chdir($ilias_root);
require_once($ilias_root . '/Services/Init/classes/class.ilInitialisation.php');
ilContext::init(ilContext::CONTEXT_WEB);
ilInitialisation::initILIAS();

// Include required classes
require_once(__DIR__ . '/classes/platform/class.AIChatPageComponentException.php');
require_once(__DIR__ . '/classes/platform/class.AIChatPageComponentConfig.php');
require_once(__DIR__ . '/classes/objects/class.AIChatPageComponentMessage.php');
require_once(__DIR__ . '/classes/objects/class.AIChatPageComponentChat.php');
require_once(__DIR__ . '/classes/ai/class.AIChatPageComponentLLM.php');
require_once(__DIR__ . '/classes/ai/class.AIChatPageComponentRAMSES.php');
require_once(__DIR__ . '/classes/ai/class.AIChatPageComponentOpenAI.php');

global $DIC;

// Set JSON headers
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

// Handle CORS if needed
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    http_response_code(200);
    exit();
}

try {
    // Check authentication
    if (!$DIC->user() || $DIC->user()->getId() == ANONYMOUS_USER_ID) {
        error_log('AIChatPageComponent: Authentication failed - user ID: ' . ($DIC->user() ? $DIC->user()->getId() : 'null'));
        sendApiResponse(['error' => 'Authentication required'], 401);
    }

    $method = $_SERVER['REQUEST_METHOD'];
    $request = $DIC->http()->request();
    
    error_log('AIChatPageComponent: API called with method: ' . $method);
    
    if ($method === 'GET') {
        $data = [];
        foreach ($request->getQueryParams() as $key => $value) {
            $data[$key] = $value;
        }
    } elseif ($method === 'POST') {
        // Get the raw input
        $input = file_get_contents('php://input');
        error_log('AIChatPageComponent: POST input length: ' . strlen($input));
        
        if (!empty($input)) {
            // Check if it looks like JSON (starts with { or [)
            $trimmedInput = trim($input);
            if (strpos($trimmedInput, '{') === 0 || strpos($trimmedInput, '[') === 0) {
                error_log('AIChatPageComponent: Attempting to decode JSON: ' . substr($input, 0, 200));
                $data = json_decode($input, true);
                if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
                    error_log('AIChatPageComponent: JSON decode failed: ' . json_last_error_msg());
                    sendApiResponse(['error' => 'Invalid JSON in request body: ' . json_last_error_msg()], 400);
                }
                error_log('AIChatPageComponent: JSON decoded successfully');
            } else {
                // It's form data - parse it manually
                error_log('AIChatPageComponent: Parsing form data: ' . substr($input, 0, 200));
                parse_str($input, $data);
                error_log('AIChatPageComponent: Form data parsed successfully, keys: ' . implode(', ', array_keys($data)));
            }
        } else {
            error_log('AIChatPageComponent: Empty POST body, trying ILIAS request wrapper');
            // Fallback to POST data - extract from ILIAS wrapper
            $data = [];
            $parsedBody = $request->getParsedBody();
            error_log('AIChatPageComponent: Parsed body type: ' . gettype($parsedBody));
            
            if (is_array($parsedBody)) {
                $data = $parsedBody;
                error_log('AIChatPageComponent: Using parsed body array');
            } else {
                error_log('AIChatPageComponent: Trying $_POST extraction');
                // Manual extraction if needed
                foreach (['action', 'chat_id', 'message', 'system_prompt', 'max_memory', 'persistent', 'ai_service'] as $key) {
                    if (isset($_POST[$key])) {
                        $data[$key] = $_POST[$key];
                        error_log('AIChatPageComponent: Found $_POST[' . $key . '] = ' . $_POST[$key]);
                    }
                }
            }
        }
        
        // Ensure data is an array
        if (!is_array($data)) {
            error_log('AIChatPageComponent: Data is not array, converting from: ' . gettype($data));
            $data = [];
        }
    } else {
        sendApiResponse(['error' => 'Method not allowed'], 405);
    }

    $action = $data['action'] ?? '';
    
    error_log('AIChatPageComponent: Extracted action: ' . $action);
    error_log('AIChatPageComponent: Request data keys: ' . implode(', ', array_keys($data)));
    
    // Validate data is array
    if (!is_array($data)) {
        error_log('AIChatPageComponent: Data is not an array - type: ' . gettype($data));
        sendApiResponse(['error' => 'Invalid request data format'], 400);
    }

    switch ($action) {
        case 'send_message':
            $result = handleSendMessage($data);
            break;
        case 'load_chat':
        case 'get_chat':
            $result = handleLoadChat($data);
            break;
        case 'clear_chat':
            $result = handleClearChat($data);
            break;
        case 'get_available_services':
            $result = handleGetAvailableServices();
            break;
        default:
            $result = ['error' => 'Invalid action: ' . $action];
    }
    
    sendApiResponse($result);

} catch (Exception $e) {
    error_log('AIChatPageComponent API Error: ' . $e->getMessage());
    sendApiResponse(['error' => 'Internal server error'], 500);
}

function parseBooleanParam($value): bool
{
    if (is_string($value)) {
        return strtolower($value) === 'true' || $value === '1';
    }
    return filter_var($value, FILTER_VALIDATE_BOOLEAN);
}

function handleSendMessage(array $data) : array
{
    global $DIC;
    
    $chat_id = $data['chat_id'] ?? '';
    $user_message = $data['message'] ?? '';
    $system_prompt = $data['system_prompt'] ?? '';
    $max_memory = (int)($data['max_memory'] ?? 10);
    $persistent = parseBooleanParam($data['persistent'] ?? false);
    $ai_service = $data['ai_service'] ?? 'default';
    
    // Debug logging
    error_log('AIChatPageComponent: handleSendMessage called with ai_service=' . $ai_service);
    error_log('AIChatPageComponent: persistent raw value: ' . ($data['persistent'] ?? 'not set'));
    error_log('AIChatPageComponent: persistent processed: ' . ($persistent ? 'true' : 'false'));
    error_log('AIChatPageComponent: Request data: ' . json_encode($data));
    
    if (empty($chat_id) || empty($user_message)) {
        error_log('AIChatPageComponent: Missing required parameters - chat_id=' . $chat_id . ', message length=' . strlen($user_message));
        return ['error' => 'Missing required parameters'];
    }
    
    // Create or load chat
    $chat = new \objects\AIChatPageComponentChat($chat_id, $persistent);
    $chat->setUserId($DIC->user()->getId());
    $chat->setMaxMessages($max_memory);
    
    // Add user message
    $userMessage = new \objects\AIChatPageComponentMessage();
    $userMessage->setChatId($chat_id);
    $userMessage->setRole('user');
    $userMessage->setMessage($user_message);
    $chat->addMessage($userMessage);
    
    // Initialize LLM based on service selection
    error_log('AIChatPageComponent: Creating LLM instance for service: ' . $ai_service);
    $llm = createLLMInstance($ai_service);
    error_log('AIChatPageComponent: Created LLM instance: ' . get_class($llm));
    $llm->setPrompt($system_prompt);
    $llm->setMaxMemoryMessages($max_memory);
    
    // Send to AI
    error_log('AIChatPageComponent: Sending chat to AI service...');
    try {
        $aiResponse = $llm->sendChat($chat);
        error_log('AIChatPageComponent: AI response received, length: ' . strlen($aiResponse));
    } catch (Exception $e) {
        error_log('AIChatPageComponent: AI service failed: ' . $e->getMessage());
        return ['error' => 'AI service error: ' . $e->getMessage()];
    }
    
    // Add AI response
    $aiMessage = new \objects\AIChatPageComponentMessage();
    $aiMessage->setChatId($chat_id);
    $aiMessage->setRole('assistant');
    $aiMessage->setMessage($aiResponse);
    $chat->addMessage($aiMessage);
    
    // Save chat
    $chat->save();
    
    return [
        'success' => true,
        'message' => $aiResponse,
        'chat' => $chat->toArray()
    ];
}

function handleLoadChat(array $data) : array
{
    $chat_id = $data['chat_id'] ?? '';
    $persistent = parseBooleanParam($data['persistent'] ?? false);
    
    if (empty($chat_id)) {
        return ['error' => 'Missing chat_id parameter'];
    }
    
    $chat = new \objects\AIChatPageComponentChat($chat_id, $persistent);
    
    return [
        'success' => true,
        'chat' => $chat->toArray()
    ];
}

function handleClearChat(array $data) : array
{
    $chat_id = $data['chat_id'] ?? '';
    $persistent = parseBooleanParam($data['persistent'] ?? false);
    
    if (empty($chat_id)) {
        return ['error' => 'Missing chat_id parameter'];
    }
    
    $chat = new \objects\AIChatPageComponentChat($chat_id, $persistent);
    $chat->delete();
    
    return [
        'success' => true,
        'message' => 'Chat cleared'
    ];
}

function handleGetAvailableServices() : array
{
    return ['services' => getAvailableAIServices()];
}

function getAvailableAIServices() : array
{
    $services = ['default' => 'Use Global Default'];
    
    try {
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
        error_log('AIChatPageComponent: Failed to load available services: ' . $e->getMessage());
        // Fallback to basic options
        $services['openai'] = 'OpenAI GPT';
        $services['ramses'] = 'RAMSES';
    }
    
    return $services;
}

function createLLMInstance(string $service)
{
    error_log('AIChatPageComponent: createLLMInstance called with service: ' . $service);
    
    switch ($service) {
        case 'openai':
            error_log('AIChatPageComponent: Creating OpenAI instance');
            return \ai\AIChatPageComponentOpenAI::createFromAIChatConfig();
        case 'ramses':
            error_log('AIChatPageComponent: Creating RAMSES instance');
            return \ai\AIChatPageComponentRAMSES::createFromAIChatConfig();
        case 'default':
        default:
            error_log('AIChatPageComponent: Using default LLM selection for service: ' . $service);
            // Temporarily force OpenAI to avoid RAMSES SSL issues
            error_log('AIChatPageComponent: Forcing OpenAI for debugging');
            return \ai\AIChatPageComponentOpenAI::createFromAIChatConfig();
    }
}

function getDefaultLLMInstance()
{
    try {
        $available_services = \platform\AIChatPageComponentConfig::get('available_services');
        
        // Priority order: OpenAI -> RAMSES -> fallback
        if (is_array($available_services)) {
            if (isset($available_services['openai']) && $available_services['openai'] == "1") {
                error_log('AIChatPageComponent: Using OpenAI as default service');
                return \ai\AIChatPageComponentOpenAI::createFromAIChatConfig();
            }
            if (isset($available_services['ramses']) && $available_services['ramses'] == "1") {
                error_log('AIChatPageComponent: Using RAMSES as default service');
                return \ai\AIChatPageComponentRAMSES::createFromAIChatConfig();
            }
        }
        
        // Fallback to OpenAI instead of RAMSES due to SSL issues
        error_log('AIChatPageComponent: No services configured, falling back to OpenAI');
        return \ai\AIChatPageComponentOpenAI::createFromAIChatConfig();
        
    } catch (Exception $e) {
        error_log('AIChatPageComponent: Failed to determine default LLM, falling back to OpenAI: ' . $e->getMessage());
        return \ai\AIChatPageComponentOpenAI::createFromAIChatConfig();
    }
}

function sendApiResponse($data, int $httpCode = 200): void
{
    http_response_code($httpCode);
    echo json_encode($data);
    exit();
}