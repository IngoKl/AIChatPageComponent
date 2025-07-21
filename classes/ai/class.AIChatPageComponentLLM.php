<?php
declare(strict_types=1);

namespace ai;

use objects\AIChatPageComponentChat;
use platform\AIChatPageComponentException;

/**
 * Abstract Class AIChatPageComponentLLM
 * Based on LLM from AIChat plugin, adapted for PageComponent
 */
abstract class AIChatPageComponentLLM
{
    public abstract function sendChat(AIChatPageComponentChat $chat);
    
    private ?int $max_memory_messages = null;
    private ?string $prompt = null;

    public function getMaxMemoryMessages(): ?int
    {
        return $this->max_memory_messages;
    }

    public function setMaxMemoryMessages(?int $max_memory_messages): void
    {
        $this->max_memory_messages = $max_memory_messages;
    }

    public function getPrompt(): ?string
    {
        return $this->prompt;
    }

    public function setPrompt(?string $prompt): void
    {
        $this->prompt = $prompt;
    }

    /**
     * Convert chat to messages array for API
     * @throws AIChatPageComponentException
     */
    protected function chatToMessagesArray(AIChatPageComponentChat $chat): array
    {
        $messages = [];

        foreach ($chat->getMessages() as $message) {
            $messages[] = [
                "role" => $message->getRole(),
                "content" => $message->getMessage()
            ];
        }

        $max_memory_messages = $this->getMaxMemoryMessages();

        if (isset($max_memory_messages)) {
            $max_memory_messages = intval($max_memory_messages);
        } else {
            $max_memory_messages = 0;
        }

        if ($max_memory_messages > 0) {
            $messages = array_slice($messages, -$max_memory_messages);
        }

        $prompt = $this->getPrompt();

        if (isset($prompt) && !empty(trim($prompt))) {
            array_unshift($messages, [
                "role" => "system",
                "content" => $prompt
            ]);
        }

        return $messages;
    }
}