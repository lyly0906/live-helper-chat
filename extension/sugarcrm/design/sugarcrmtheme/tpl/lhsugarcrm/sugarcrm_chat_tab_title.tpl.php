<li role="presentation"><a onclick="sugarcrm.loadLead('<?php if (isset($chat->chat_variables_array['sugarcrm_lead_id']) && $chat->chat_variables_array['sugarcrm_lead_id'] != '') : ?><?php echo $chat->chat_variables_array['sugarcrm_lead_id']?><?php endif;?>',<?php echo $chat->id?>)" href="#main-extension-sugarcrm-chat-<?php echo $chat->id?>" aria-controls="main-extension-sugarcrm-chat-<?php echo $chat->id?>" role="tab" data-toggle="tab" title="SugarCRM">CRM</a></li>