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
 * AI Chat Page Component plugin
 * @author Ingo Kleiber <ingo.kleiber@uni-koeln.de>
 */
class ilAIChatPageComponentPlugin extends ilPageComponentPlugin
{
    /**
     * Get plugin name
     * @return string
     */
    public function getPluginName() : string
    {
        return "AIChatPageComponent";
    }

    /**
     * Check if parent type is valid
     */
    public function isValidParentType(string $a_parent_type) : bool
    {
        // test with all parent types
        return true;
    }

    /**
     * Handle an event
     * @param string $a_component
     * @param string $a_event
     * @param mixed  $a_parameter
     */
    public function handleEvent(string $a_component, string $a_event, $a_parameter) : void
    {
        $_SESSION['pcaic_listened_event'] = array('time' => time(), 'event' => $a_event);
    }

    /**
     * This function is called when the page content is cloned
     * @param array  $a_properties     properties saved in the page, (should be modified if neccessary)
     * @param string $a_plugin_version plugin version of the properties
     */
    public function onClone(array &$a_properties, string $a_plugin_version) : void
    {
        // Clone additional data if it exists
        if ($additional_data_id = ($a_properties['additional_data_id'] ?? null)) {
            $data = $this->getData($additional_data_id);
            if ($data) {
                $id = $this->saveData($data);
                $a_properties['additional_data_id'] = $id;
            }
        }
        
        // Generate new chat ID for cloned component
        if (isset($a_properties['chat_id'])) {
            $a_properties['chat_id'] = uniqid('aichat_', true);
        }
    }

    /**
     * This function is called before the page content is deleted
     * @param array  $a_properties     properties saved in the page (will be deleted afterwards)
     * @param string $a_plugin_version plugin version of the properties
     */
    public function onDelete(array $a_properties, string $a_plugin_version, bool $move_operation = false) : void
    {
        if ($move_operation) {
            return;
        }

        // Clean up any additional data stored for this AI Chat component
        if ($additional_data_id = ($a_properties['additional_data_id'] ?? null)) {
            $this->deleteData($additional_data_id);
        }
        
        // Clean up any chat messages stored for this component
        if ($chat_id = ($a_properties['chat_id'] ?? null)) {
            $this->deleteChatMessages($chat_id);
        }
    }

    /**
     * Recursively copy directory (taken from php manual)
     * @param string $src
     * @param string $dst
     */
    private function rCopy(string $src, string $dst) : void
    {
        $dir = opendir($src);
        if (!is_dir($dst)) {
            mkdir($dst);
        }
        while (false !== ($file = readdir($dir))) {
            if (($file != '.') && ($file != '..')) {
                if (is_dir($src . '/' . $file)) {
                    $this->rCopy($src . '/' . $file, $dst . '/' . $file);
                } else {
                    copy($src . '/' . $file, $dst . '/' . $file);
                }
            }
        }
        closedir($dir);
    }

    /**
     * Get additional data by id
     */
    public function getData(int $id) : ?string
    {
        global $DIC;
        $db = $DIC->database();

        $query = "SELECT data FROM pcaic_data WHERE id = " . $db->quote($id, 'integer');
        $result = $db->query($query);
        if ($row = $db->fetchAssoc($result)) {
            return $row['data'];
        }
        return null;
    }

    /**
     * Save new additional data
     */
    public function saveData(string $data) : int
    {
        global $DIC;
        $db = $DIC->database();

        $id = $db->nextId('pcaic_data');
        $db->insert(
            'pcaic_data',
            array(
                'id' => array('integer', $id),
                'data' => array('text', $data)
            )
        );
        return $id;
    }

    /**
     * Update additional data
     */
    public function updateData(int $id, string $data) : void
    {
        global $DIC;
        $db = $DIC->database();

        $db->update(
            'pcaic_data',
            array(
                'data' => array('text', $data)
            ),
            array(
                'id' => array('integer', $id)
            )
        );
    }

    /**
     * Delete additional data
     */
    public function deleteData(int $id) : void
    {
        global $DIC;
        $db = $DIC->database();

        $query = "DELETE FROM pcaic_data WHERE id = " . $db->quote($id, 'integer');
        $db->manipulate($query);
    }

    /**
     * Delete chat messages for a specific chat ID
     */
    public function deleteChatMessages(string $chat_id) : void
    {
        global $DIC;
        $db = $DIC->database();

        $query = "DELETE FROM pcaic_messages WHERE chat_id = " . $db->quote($chat_id, 'text');
        $db->manipulate($query);
    }
}