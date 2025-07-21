<?php
declare(strict_types=1);

namespace objects;

use DateTime;
use platform\AIChatPageComponentException;

/**
 * Class AIChatPageComponentChat
 * Based on Chat from AIChat plugin, adapted for PageComponent
 */
class AIChatPageComponentChat
{
    private string $id;
    private string $title;
    private DateTime $created_at;
    private int $user_id = 0;
    private DateTime $last_update;
    private array $messages = array();
    private ?int $max_messages = null;
    private bool $persistent = false;

    public function __construct(?string $id = null, bool $persistent = false)
    {
        $this->created_at = new DateTime();
        $this->last_update = new DateTime();
        $this->persistent = $persistent;
        
        if ($id !== null) {
            $this->id = $id;
            if ($persistent) {
                $this->loadFromDB();
            } else {
                $this->loadFromSession();
            }
        } else {
            $this->id = uniqid('chat_', true);
        }
        
        $this->setTitle();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(?string $title = null): void
    {
        if ($title === null) {
            $this->title = "AI Chat - " . $this->created_at->format("Y-m-d H:i:s");
        } else {
            $this->title = $title;
        }
    }

    public function getCreatedAt(): DateTime
    {
        return $this->created_at;
    }

    public function setCreatedAt(DateTime $created_at): void
    {
        $this->created_at = $created_at;
    }

    public function getUserId(): int
    {
        return $this->user_id;
    }

    public function setUserId(int $user_id): void
    {
        $this->user_id = $user_id;
    }

    public function getLastUpdate(): DateTime
    {
        return $this->last_update;
    }

    public function setLastUpdate(DateTime $last_update): void
    {
        $this->last_update = $last_update;
    }

    public function getMessages(): array
    {
        return $this->messages;
    }

    public function addMessage(AIChatPageComponentMessage $message): void
    {
        $this->messages[] = $message;
        $this->last_update = new DateTime();
    }

    public function setMaxMessages(int $max_messages): void
    {
        $this->max_messages = $max_messages;
    }

    public function getMaxMessages(): ?int
    {
        return $this->max_messages;
    }

    public function isPersistent(): bool
    {
        return $this->persistent;
    }

    public function setPersistent(bool $persistent): void
    {
        $this->persistent = $persistent;
    }

    /**
     * Load chat from database
     * @throws AIChatPageComponentException
     */
    public function loadFromDB(): void
    {
        global $DIC;
        
        if (!isset($DIC) || !$DIC->database()) {
            throw new AIChatPageComponentException("ILIAS DIC not available or database not initialized");
        }
        
        $db = $DIC->database();
        $user_id = $DIC->user() ? $DIC->user()->getId() : 0;

        try {
            // Load messages for this chat and user
            $result = $db->query(
                "SELECT * FROM pcaic_messages WHERE chat_id = " . $db->quote($this->getId(), 'text') . 
                " AND user_id = " . $db->quote($user_id, 'integer') .
                " ORDER BY timestamp ASC"
            );
            
            while ($row = $db->fetchAssoc($result)) {
                $message = new AIChatPageComponentMessage();
                $message->setId((int)$row["id"]);
                $message->setChatId($row["chat_id"]);
                $message->setDate(new DateTime($row["timestamp"]));
                $message->setRole($row["role"]);
                $message->setMessage($row["message"]);
                $this->addMessage($message);
            }
        } catch (\Exception $e) {
            throw new AIChatPageComponentException("Failed to load chat from database: " . $e->getMessage());
        }
    }

    /**
     * Load chat from session
     */
    public function loadFromSession(): void
    {
        $messages = $_SESSION['pcaic_messages'] ?? [];

        // Sort messages by timestamp
        usort($messages, function ($a, $b) {
            return strtotime($a["timestamp"]) - strtotime($b["timestamp"]);
        });

        foreach ($messages as $messageData) {
            if ($messageData["chat_id"] == $this->getId()) {
                $message = new AIChatPageComponentMessage();
                $message->setId($messageData["id"]);
                $message->setChatId($messageData["chat_id"]);
                $message->setDate(new DateTime($messageData["timestamp"]));
                $message->setRole($messageData["role"]);
                $message->setMessage($messageData["message"]);
                $this->addMessage($message);
            }
        }
    }

    /**
     * Save chat (messages are saved individually)
     */
    public function save(): void
    {
        if ($this->persistent) {
            foreach ($this->messages as $message) {
                $message->save();
            }
        } else {
            $this->saveToSession();
        }
    }

    /**
     * Save chat to session
     */
    public function saveToSession(): void
    {
        foreach ($this->messages as $message) {
            $message->saveToSession();
        }
    }

    /**
     * Delete chat and all its messages
     * @throws AIChatPageComponentException
     */
    public function delete(): void
    {
        if ($this->persistent) {
            global $DIC;
            
            if (!isset($DIC) || !$DIC->database()) {
                throw new AIChatPageComponentException("ILIAS DIC not available or database not initialized");
            }
            
            $db = $DIC->database();
            $user_id = $DIC->user() ? $DIC->user()->getId() : 0;
            
            try {
                $db->manipulate(
                    "DELETE FROM pcaic_messages WHERE chat_id = " . $db->quote($this->getId(), 'text') .
                    " AND user_id = " . $db->quote($user_id, 'integer')
                );
            } catch (\Exception $e) {
                throw new AIChatPageComponentException("Failed to delete chat: " . $e->getMessage());
            }
        } else {
            $this->deleteFromSession();
        }
    }

    /**
     * Delete chat from session
     */
    public function deleteFromSession(): void
    {
        $messages = $_SESSION['pcaic_messages'] ?? [];

        foreach ($messages as $key => $message) {
            if ($message["chat_id"] == $this->getId()) {
                unset($messages[$key]);
            }
        }

        $_SESSION['pcaic_messages'] = $messages;
    }

    /**
     * Convert chat to array
     */
    public function toArray(): array
    {
        $messages = array();

        foreach ($this->messages as $message) {
            $messages[] = $message->toArray();
        }

        // Apply max message limit
        if ($this->max_messages !== null && $this->max_messages > 0) {
            $messages = array_slice($messages, -$this->max_messages);
        }

        return [
            "id" => $this->getId(),
            "title" => $this->getTitle(),
            "created_at" => $this->getCreatedAt()->format("Y-m-d H:i:s"),
            "user_id" => $this->getUserId(),
            "messages" => $messages,
            "last_update" => $this->getLastUpdate()->format("Y-m-d H:i:s"),
            "persistent" => $this->isPersistent()
        ];
    }
}