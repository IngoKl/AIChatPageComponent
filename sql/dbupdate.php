<#1>
<?php
/* Copyright (c) 1998-2025 ILIAS open source, Extended GPL, see docs/LICENSE  */

/**
 * AI Chat Page Component plugin: database update script
 * @author Ingo Kleiber <ingo.kleiber@uni-koeln.de>
 */ 

/**
 * Additional data storage for AI chat configurations
 */
if(!$ilDB->tableExists('pcaic_data'))
{
    $fields = array(
        'id' => array(
            'type' => 'integer',
            'length' => 4,
            'notnull' => true,
            'default' => 0
        ),

        'data' => array(
            'type' => 'text',
            'length' => 4000,
            'notnull' => false,
        ),
    );
    $ilDB->createTable('pcaic_data', $fields);
    $ilDB->addPrimaryKey('pcaic_data', array('id'));
    $ilDB->createSequence('pcaic_data');
}

/**
 * Chat messages storage for AI chat components
 */
if(!$ilDB->tableExists('pcaic_messages'))
{
    $fields = array(
        'id' => array(
            'type' => 'integer',
            'length' => 8,
            'notnull' => true,
            'default' => 0
        ),
        'chat_id' => array(
            'type' => 'text',
            'length' => 255,
            'notnull' => true,
        ),
        'user_id' => array(
            'type' => 'integer',
            'length' => 8,
            'notnull' => false,
        ),
        'role' => array(
            'type' => 'text',
            'length' => 50,
            'notnull' => true,
        ),
        'message' => array(
            'type' => 'text',
            'length' => 4000,
            'notnull' => true,
        ),
        'timestamp' => array(
            'type' => 'timestamp',
            'notnull' => true,
        ),
    );
    $ilDB->createTable('pcaic_messages', $fields);
    $ilDB->addPrimaryKey('pcaic_messages', array('id'));
    $ilDB->createSequence('pcaic_messages');
}
?>