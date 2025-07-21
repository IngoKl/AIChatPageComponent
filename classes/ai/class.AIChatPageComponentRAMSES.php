<?php
declare(strict_types=1);

namespace ai;

use objects\AIChatPageComponentChat;
use platform\AIChatPageComponentException;
use platform\AIChatPageComponentConfig;

/**
 * Class AIChatPageComponentRAMSES
 * Based on RAMSES from AIChat plugin, adapted for PageComponent
 */
class AIChatPageComponentRAMSES extends AIChatPageComponentLLM
{
    private string $model;
    private string $apiKey;
    private bool $streaming = false;
    
    public const MODEL_TYPES = [
        "mistral-small-3-2-24b-instruct-2506" => "Mistral small 3.2.24b"
    ];

    public function __construct(string $model)
    {
        $this->model = $model;
    }

    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    public function setApiKey(string $apiKey): void
    {
        $this->apiKey = $apiKey;
    }

    public function setStreaming(bool $streaming): void
    {
        $this->streaming = $streaming;
    }

    public function isStreaming(): bool
    {
        return $this->streaming;
    }

    /**
     * Send chat to RAMSES API
     * @throws AIChatPageComponentException
     */
    public function sendChat(AIChatPageComponentChat $chat)
    {
        global $DIC;

        $apiUrl = 'https://ramses-oski.itcc.uni-koeln.de/v1/chat/completions';

        $payload = json_encode([
            "messages" => $this->chatToMessagesArray($chat),
            "model" => $this->model,
            "temperature" => 0.5,
            "stream" => $this->isStreaming()
        ]);

        $curlSession = curl_init();

        // Get certificate path from AIChat plugin
        $plugin = \ilAIChatPlugin::getInstance();
        $plugin_path = $plugin->getDirectory();
        $absolute_plugin_path = realpath($plugin_path);
        $ca_cert_path = $absolute_plugin_path . '/certs/RAMSES.pem';

        if (file_exists($ca_cert_path)) {
            curl_setopt($curlSession, CURLOPT_CAINFO, $ca_cert_path);
        } else {
            // Fallback: disable SSL verification for development/testing
            // WARNING: This should not be used in production
            curl_setopt($curlSession, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curlSession, CURLOPT_SSL_VERIFYHOST, false);
            error_log('AIChatPageComponent: SSL certificate not found, disabling SSL verification');
        }

        curl_setopt($curlSession, CURLOPT_URL, $apiUrl);
        curl_setopt($curlSession, CURLOPT_POST, true);
        curl_setopt($curlSession, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($curlSession, CURLOPT_RETURNTRANSFER, !$this->isStreaming());
        curl_setopt($curlSession, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->getApiKey()
        ]);

        // Handle proxy settings
        if (class_exists('ilProxySettings') && \ilProxySettings::_getInstance()->isActive()) {
            $proxyHost = \ilProxySettings::_getInstance()->getHost();
            $proxyPort = \ilProxySettings::_getInstance()->getPort();
            $proxyURL = $proxyHost . ":" . $proxyPort;
            curl_setopt($curlSession, CURLOPT_PROXY, $proxyURL);
        }

        $responseContent = '';

        if ($this->isStreaming()) {
            curl_setopt($curlSession, CURLOPT_WRITEFUNCTION, function ($curlSession, $chunk) use (&$responseContent) {
                $responseContent .= $chunk;
                echo $chunk;
                ob_flush();
                flush();
                return strlen($chunk);
            });
        }

        $response = curl_exec($curlSession);
        $httpcode = curl_getinfo($curlSession, CURLINFO_HTTP_CODE);
        $errNo = curl_errno($curlSession);
        $errMsg = curl_error($curlSession);
        curl_close($curlSession);

        if ($errNo) {
            throw new AIChatPageComponentException("HTTP Error: " . $errMsg);
        }

        if ($httpcode != 200) {
            // Try to parse error response for more details
            $errorData = json_decode($response, true);
            $errorMessage = $errorData['error']['message'] ?? "HTTP Error: " . $httpcode;
            
            if ($httpcode === 401) {
                throw new AIChatPageComponentException("Invalid API key: " . $errorMessage, 401);
            } else {
                throw new AIChatPageComponentException($errorMessage, $httpcode);
            }
        }

        if (!$this->isStreaming()) {
            $decodedResponse = json_decode($response, true);
            if ($decodedResponse === null && json_last_error() !== JSON_ERROR_NONE) {
                throw new AIChatPageComponentException("Invalid JSON response from RAMSES API: " . json_last_error_msg());
            }
            if (!isset($decodedResponse['choices'][0]['message']['content'])) {
                throw new AIChatPageComponentException("Unexpected API response structure from RAMSES");
            }
            return $decodedResponse['choices'][0]['message']['content'];
        }

        // Process streaming response
        $messages = explode("\n", $responseContent);
        $completeMessage = '';

        foreach ($messages as $message) {
            if (trim($message) !== '' && strpos($message, 'data: ') === 0) {
                $jsonData = substr($message, strlen('data: '));
                if ($jsonData === '[DONE]') {
                    continue;
                }
                $json = json_decode($jsonData, true);
                if ($json === null && json_last_error() !== JSON_ERROR_NONE) {
                    continue; // Skip invalid JSON chunks
                }
                if (is_array($json) && isset($json['choices'][0]['delta']['content'])) {
                    $completeMessage .= $json['choices'][0]['delta']['content'];
                }
            }
        }

        return $completeMessage;
    }

    /**
     * Initialize RAMSES with AIChat configuration
     * @throws AIChatPageComponentException
     */
    public static function createFromAIChatConfig(): self
    {
        try {
            // Get default model from AIChat config
            $model = AIChatPageComponentConfig::get('ramses_model') ?: 'mistral-small-3-2-24b-instruct-2506';
            
            $ramses = new self($model);
            
            // Get API key from AIChat config
            $apiKey = AIChatPageComponentConfig::get('ramses_api_key') ?: '';
            $ramses->setApiKey($apiKey);
            
            return $ramses;
        } catch (\Exception $e) {
            throw new AIChatPageComponentException("Failed to create RAMSES instance from AIChat config: " . $e->getMessage());
        }
    }
}