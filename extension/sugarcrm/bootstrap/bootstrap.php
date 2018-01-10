<?php

/**
 * Direct integration with SugarCRM
 * */
class erLhcoreClassExtensionSugarcrm
{

    public function __construct()
    {}

    public function run()
    {
        $dispatcher = erLhcoreClassChatEventDispatcher::getInstance();
        
        $dispatcher->listen('chat.chat_offline_request', array(
            $this,
            'offlineRequest'
        ));
    }

    public function __get($var)
    {
        switch ($var) {
            
            case 'settings':
                $this->settings = erLhcoreClassModelChatConfig::fetch('sugarcrm_data')->data;
                return $this->settings;
                break;
            
            default:
                ;
                break;
        }
    }

    public function offlineRequest($params)
    {
        if (isset($this->settings['sugarcrm_offline_lead']) && $this->settings['sugarcrm_offline_lead'] == true && isset($this->settings['sugarcrm_enabled']) && $this->settings['sugarcrm_enabled'] == true) {
            $chat = $params['chat'];
            $inputData = $params['input_data'];

            $soapclient = new SoapClient($this->settings['wsdl_address']);
            
            $result_array = $soapclient->login(array(
                'user_name' => $this->settings['wsdl_username'],
                'password' => $this->settings['wsdl_password'],
                'version' => '0.1'
            ), 'soaplhcsugarcrm');
            $session_id = $result_array->id;
            $user_guid = $soapclient->get_user_id($session_id);
            
            $leadData = array(
                array(
                    'name' => 'lastname',
                    'value' => $chat->nick
                ),
                array(
                    'name' => 'department',
                    'value' => (string) $chat->department
                ),
                array(
                    'name' => 'status',
                    'value' => 'New'
                ),
                array(
                    'name' => 'phone',
                    'value' => (string) $chat->phone
                ),
                array(
                    'name' => 'email1',
                    'value' => (string) $chat->email
                ),
                array(
                    'name' => 'lead_source',
                    'value' => 'Web Site'
                ),
                array(
                    'name' => 'website',
                    'value' => (string) $chat->referrer
                ),
                array(
                    'name' => 'lead_source_description',
                    'value' => PHP_EOL.$inputData->question."\n\n".erTranslationClassLhTranslation::getInstance()->getTranslation('sugarcrm/module', 'Offline form request')
                ),
                array(
                    'name' => 'assigned_user_id',
                    'value' => $user_guid
                )
            );
            
            $chatAdditionalData = $chat->additional_data_array;
            
            // Add custom fields if required
            if (isset($this->settings['lead_extra_fields']) && is_array($this->settings['lead_extra_fields']) && ! empty($this->settings['lead_extra_fields']) && is_array($chatAdditionalData) && ! empty($chatAdditionalData)) {
            
                $fieldsMappingSugar = array();
                foreach ($this->settings['lead_extra_fields'] as $data) {
                    if (isset($data['lhcfield']) && ! empty($data['lhcfield'])) {
                        $fieldsMappingSugar[$data['lhcfield']] = $data['sugarcrm'];
                    }
                }

                foreach ($chatAdditionalData as $addItem) {
                    $fieldIdentifier = isset($addItem->identifier) ? $addItem->identifier : str_replace(' ', '_', $addItem->key);
                    if (key_exists($fieldIdentifier, $fieldsMappingSugar)) {
                        $leadData[] = array(
                            'name' => $fieldsMappingSugar[$fieldIdentifier],
                            'value' => $addItem->value
                        );
                    }
                }
            }
            
            $result = $soapclient->set_entry($session_id, 'Leads', $leadData);            
        }
    }
    
    /***
     * Fetches single entry data
     * 
     * @param string $leadId
     */
    public function getLeadById($leadId) {
        file_put_contents("log.txt", "---getLeadById---start----", FILE_APPEND);
        $resultgettoken = $this->getToken();
        if($resultgettoken['success']) {
            $token = $resultgettoken['result']['token'];
            $resultuserinfo = $this->getSessionId($token);
            if ($resultuserinfo['success']) {
                $session_id = $resultuserinfo['result']['sessionName'];
                $user_guid = $resultuserinfo['result']['userId'];
                $ch = curl_init();
                $url = $this->settings['wsdl_address']."?operation=retrieve&sessionName=".$session_id."&id=".$leadId;
                file_put_contents("log.txt", "---getLeadById---start----".var_export($url,true), FILE_APPEND);
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT , 5);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                @curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Some hostings produces wargning...
                $output = curl_exec($ch);
                curl_close($ch);

                $outputpos = strpos($output, "{", 0);
                $output = substr($output, $outputpos);
                $resultleadinfo = json_decode($output);
                if($resultleadinfo->success==true){
                    $result = $resultleadinfo->result;
                }

            }
        }
                
//        $result = $soapclient->get_entry( $session_id, "Leads", $leadId);
        if (isset($result)) {
            return $result;
        }
       
        return false;
    }
    
    public function getFieldsForUpdate()
    {
        return array(

            'firstname' => array(
                'title' => erTranslationClassLhTranslation::getInstance()->getTranslation('sugarcrm/module','First name')
            ),
            'lastname' => array(
                'title' => erTranslationClassLhTranslation::getInstance()->getTranslation('sugarcrm/module','Last name')
            ),
            'company' => array(
                'title' => erTranslationClassLhTranslation::getInstance()->getTranslation('sugarcrm/module','Company')
            ),
            'phone' => array(
                'title' =>  erTranslationClassLhTranslation::getInstance()->getTranslation('sugarcrm/module', 'Phone work')
            ),
            'mobile' => array(
                'title' => erTranslationClassLhTranslation::getInstance()->getTranslation('sugarcrm/module','Phone mobile')
            ),
            'email' => array(
                'title' => erTranslationClassLhTranslation::getInstance()->getTranslation('sugarcrm/module','E-mail')
            ),

            'website' => array(
                'title' => erTranslationClassLhTranslation::getInstance()->getTranslation('sugarcrm/module','Website')
            ),

            'createdtime' => array(
                'title' => erTranslationClassLhTranslation::getInstance()->getTranslation('sugarcrm/module','Entered'),
                'disabled' => true
            ),
            'modifiedtime' => array(
                'title' => erTranslationClassLhTranslation::getInstance()->getTranslation('sugarcrm/module','Modified'),
                'disabled' => true
            ),
            'assigned_user_id' => array(
                'title' => erTranslationClassLhTranslation::getInstance()->getTranslation('sugarcrm/module','assigned_user_id'),
                'type' => 'hidden',
                'display' => 'none'
            ),
            'description' => array(
                'title' => erTranslationClassLhTranslation::getInstance()->getTranslation('sugarcrm/module','Description'),
                'type' => 'textarea'
            ),
            'lead_source_description' => array(
                'title' => 'Lead source description','type' => 'textarea'
            )
        );
    }
    
    public function doUpdateLeadId($leadId) {
        file_put_contents("log.txt", "---doUpdateLeadId-----".var_export($leadId,true), FILE_APPEND);
        $resultgettoken = $this->getToken();
        if($resultgettoken['success']){
            $token = $resultgettoken['result']['token'];
            $resultuserinfo = $this->getSessionId($token);
            if($resultuserinfo['success']){
                $session_id = $resultuserinfo['result']['sessionName'];
                $user_guid = $resultuserinfo['result']['userId'];
            }
        }
                        
        $leadData = array( 'id' => $leadId);
        $leadFields = $this->getFieldsForUpdate();
        
        foreach ($leadFields as $key => $field) {
            if (!isset($field['disabled']) || $field['disabled'] == false){
                $leadData[$key] = isset($_POST[$key]) ? $_POST[$key] : '';
            }
        }
        $leadData['assigned_user_id'] = $user_guid;
        file_put_contents("log.txt", "--update----------leadData-----".var_export($leadData,true), FILE_APPEND);
        //修改潜在客户信息
        $post_data = array ("sessionName" => $session_id,"operation" => 'update','element'=>json_encode($leadData));
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->settings['wsdl_address']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT , 5);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        @curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Some hostings produces wargning...
        $output = curl_exec($ch);
        curl_close($ch);
        //file_put_contents("log.txt", "---doupateoutput-----".$output, FILE_APPEND);

//        $result = $soapclient->set_entry($session_id, 'Leads', $leadData);
        
        if ($output) {
            //获取潜在客户的信息
            return $this->getLeadById($leadId);
        }
        
        return false;
    }
    
    /***
     * $sugarcrm = erLhcoreClassModule::getExtensionInstance('erLhcoreClassExtensionSugarcrm');    
     * $sugarcrm->searchByModule(array('leads.phone' => '<some phone>'));
     * */
    public function searchByModule($searchParams = array(), $module = 'Leads')
    {
        //file_put_contents("log.txt", "---searchByModule-----".var_export($searchParams,true), FILE_APPEND);
        $resultgettoken = $this->getToken();
        if($resultgettoken['success']){
            $token = $resultgettoken['result']['token'];
            $resultuserinfo = $this->getSessionId($token);
            if($resultuserinfo['success']){
                $session_id = $resultuserinfo['result']['sessionName'];
                $user_guid = $resultuserinfo['result']['userId'];
            }
            $count = count($searchParams);
            $num = 1;
            $where = "";
            foreach ($searchParams as $keys=>$vals){
                if($count==1){
                    $where = $keys."='".$vals."'";
                }else{
                    if($num==1){
                        $where = $keys."='".$vals."'";
                    }else{
                        $where.= '        and   '.$keys."='".$vals."'";
                    }
                    $num++;
                }
            }

            $query = "select * from $module where  $where;";
            //file_put_contents("log.txt", "---searchByModule--query-----".$query, FILE_APPEND);
            $queryParam = urlencode($query);
            $params = "?sessionName=$session_id&operation=query&query=$queryParam";
            $url = $this->settings['wsdl_address'].$params;
            file_put_contents("log.txt", "---searchByModule--url-----".$url, FILE_APPEND);
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT , 5);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            @curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Some hostings produces wargning...
            $output = curl_exec($ch);
            curl_close($ch);
            file_put_contents("log.txt", "---searchByModule--output-----".$output, FILE_APPEND);
            $outputpos = strpos($output, "{", 0);
            $output = substr($output, $outputpos);
            $resultleadinfo = json_decode($output);
            if($resultleadinfo->success==true){
                $result = $resultleadinfo->result;
            }
        }
        if (isset($result) && count($result)==1) {
            return $result[0];
        }
        
        return false;        
    }
    
    /**
     * Creates a demo lead from SugarCRM extension configuration window
     *
     * @return unknown
     */
    public function createDemoLead()
    {
        
        $soapclient = new SoapClient($this->settings['wsdl_address']);
        
        $result_array = $soapclient->login(array(
            'user_name' => $this->settings['wsdl_username'],
            'password' => $this->settings['wsdl_password'],
            'version' => '0.1'
        ), 'soaplhcsugarcrm');
        $session_id = $result_array->id;
        $user_guid = $soapclient->get_user_id($session_id);
        
        // $LeadFields = $soapclient->get_module_fields($session_id, 'Leads'); print_r($LeadFields);
        
        $result = $soapclient->set_entry($session_id, 'Leads', array(
            array(
                'name' => 'lastname',
                'value' => 'Live Helper Chat'
            ),
            array(
                'name' => 'department',
                'value' => 'Demo departament'
            ),
            array(
                'name' => 'status',
                'value' => 'New'
            ),
            array(
                'name' => 'phone',
                'value' => 'Demo Phone'
            ),
            array(
                'name' => 'primary_address_city',
                'value' => 'Demo City'
            ),
            array(
                'name' => 'account_name',
                'value' => 'Demo account name'
            ),
            array(
                'name' => 'email1',
                'value' => 'demo@example.com'
            ),
            array(
                'name' => 'lead_source',
                'value' => 'Web Site'
            ),
            array(
                'name' => 'lead_source_description',
                'value' => 'Your lead was successfully created'
            ),
            array(
                'name' => 'assigned_user_id',
                'value' => $user_guid
            )
        ));
        
        return $result;
    }

    /**
     * Creates a general Lead by provided arguments
     * */
    public function createLeadByArray($params, $leadId = false) {
        if ($this->settings['sugarcrm_enabled'] == true) {
            $soapclient = new SoapClient($this->settings['wsdl_address']);
    
            $result_array = $soapclient->login(array(
                'user_name' => $this->settings['wsdl_username'],
                'password' => $this->settings['wsdl_password'],
                'version' => '0.1'
            ), 'soaplhcsugarcrm');
            $session_id = $result_array->id;
            $user_guid = $soapclient->get_user_id($session_id);
    
            $leadData = array(
                array(
                    'name' => 'status',
                    'value' => 'New'
                ),
                array(
                    'name' => 'assigned_user_id',
                    'value' => $user_guid
                )
            );
    
            if ($leadId !== false) {
                $leadData[] = array(
                    'name' => 'id',
                    'value' => $leadId
                );
            }
    
            foreach ($params as $additionalField) {
                $leadData[] = $additionalField;
            }
    
            $result = $soapclient->set_entry($session_id, 'Leads', $leadData);
    
            return $result;
        } else {
            throw new Exception('SugarCRM extension is not enabled');
        }
    }
    
    /**
     * Creates a lead from chat object
     *
     * @param unknown $chat            
     * @throws Exception
     * @return unknown
     */
    public function createLeadByChat(& $chat)
    {
        if ($this->settings['sugarcrm_enabled'] == true) {
            file_put_contents("log.txt", "---createLeadByChat----start----", FILE_APPEND);
            // Search for existing leads only if lead does not exists and phone is not empty
            if ((!isset($chat->chat_variables_array['sugarcrm_lead_id']) || $chat->chat_variables_array['sugarcrm_lead_id'] == '') && $chat->phone != '') {
                $leadExisting = $this->searchByModule(array('phone' => $chat->phone));
                if ($leadExisting !== false) {
                    
                    // Store associated lead data
                    $chat->chat_variables_array['sugarcrm_lead_id'] = $leadExisting->id;
                    $chat->chat_variables = json_encode($chat->chat_variables_array);
                    $chat->saveThis();
                    // Return founded lead
                    return $leadExisting;
                }
            }
            $resultgettoken = $this->getToken();
            if($resultgettoken['success']){
                $token = $resultgettoken['result']['token'];
                $resultuserinfo = $this->getSessionId($token);
                file_put_contents("log.txt", "---createLeadByChat--------".var_export($resultuserinfo,true), FILE_APPEND);
                if($resultuserinfo['success']){
                    $session_id = $resultuserinfo['result']['sessionName'];
                    $user_guid = $resultuserinfo['result']['userId'];
                }
            }
            $leadData = array(
               'firstname' => $chat->nick,
                'lastname'=>" ",
                'department' => (string) $chat->department,
                'status' => 'New',
                'phone' => (string) $chat->phone,
                'email' => (string) $chat->email,
                'company'=>(string)$chat->company,
                'leadsource' => 'Web Site',
                'website' => (string) $chat->referrer,
                'chatid'=>$chat->id,
                'assigned_user_id' => $user_guid
            );
            
            $storeLead = true;
            
            if (isset($chat->chat_variables_array['sugarcrm_lead_id']) && $chat->chat_variables_array['sugarcrm_lead_id'] != '') {
                $leadData[] = array(
                     'id' => $chat->chat_variables_array['sugarcrm_lead_id']
                );
                $storeLead = false;
            }
            
            $chatAdditionalData = $chat->additional_data_array;
            
            // Add custom fields if required
            if (isset($this->settings['lead_extra_fields']) && is_array($this->settings['lead_extra_fields']) && ! empty($this->settings['lead_extra_fields']) && is_array($chatAdditionalData) && ! empty($chatAdditionalData)) {
                
                $fieldsMappingSugar = array();
                foreach ($this->settings['lead_extra_fields'] as $data) {
                    if (isset($data['lhcfield']) && ! empty($data['lhcfield'])) {
                        $fieldsMappingSugar[$data['lhcfield']] = $data['sugarcrm'];
                    }
                }
                
                foreach ($chatAdditionalData as $addItem) {
                    $fieldIdentifier = isset($addItem->identifier) ? $addItem->identifier : str_replace(' ', '_', $addItem->key);
                    if (key_exists($fieldIdentifier, $fieldsMappingSugar)) {
                        $leadData[] = array(
                            $fieldsMappingSugar[$fieldIdentifier] => $addItem->value
                        );
                    }
                }
            }

//            $result = $soapclient->set_entry($session_id, 'Leads', $leadData);
            //添加潜在客户
            $post_data = array ("operation" => "create","format" =>"json","sessionName"=>$session_id,"elementType"=>"Leads","element"=>json_encode($leadData));
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->settings['wsdl_address']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT , 5);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            @curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Some hostings produces wargning...
            $output = curl_exec($ch);
            curl_close($ch);
            file_put_contents("log.txt", "---createLeadByChat-----".var_export($post_data,true), FILE_APPEND);
            file_put_contents("log.txt", "---createLeadByChat-----".var_export($output,true), FILE_APPEND);
            $outputpos = strpos($output,"{",0);
            $output = substr($output,$outputpos);
            $resultleadinfo = json_decode($output);
            if($resultleadinfo->success==true){
                $result = $resultleadinfo->result;
            }

            if ($result->id != - 1 && $storeLead == true) {
                $chat->chat_variables_array['sugarcrm_lead_id'] = $result->id;
                $chat->chat_variables = json_encode($chat->chat_variables_array);
                $chat->saveThis();
            }

            if ($result->id == -1) {
                throw new Exception('Lead could not be created');
            }

            return $result;
            
        } else {
            throw new Exception('SugarCRM extension is not enabled');
        }
    }

    /**
     * @return mixed获取vtiger的token
     */
    public function getToken(){
        $url = $this->settings['wsdl_address']."?operation=getchallenge&username=".$this->settings['wsdl_username'];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT , 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        @curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Some hostings produces wargning...
        $output = curl_exec($ch);
        curl_close($ch);
        $outputpos = strpos($output,"{",0);
        $output = substr($output,$outputpos);
        $resultgettoken = json_decode($output,true);
        return $resultgettoken;
    }

    public function getSessionId($token){
        $accessKey = md5($token.'s8oeXXlTSkfqeZk');
        $post_data = array ("operation" => "login","username" => $this->settings['wsdl_username'],'accessKey'=>$accessKey);
        file_put_contents("log.txt", "---getSessionId--------".var_export($post_data,true), FILE_APPEND);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->settings['wsdl_address']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT , 5);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        @curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Some hostings produces wargning...
        $output = curl_exec($ch);
        curl_close($ch);
        $outputpos = strpos($output,"{",0);
        $output = substr($output,$outputpos);
        $resultuserinfo = json_decode($output,true);
        file_put_contents("log.txt", "---getSessionId--------".var_export($resultuserinfo,true), FILE_APPEND);
        return $resultuserinfo;
    }

    /**
     * Validates lead settings
     *
     * @param unknown $settings            
     * @return multitype:NULL
     */
    public static function validateSettings(& $settings)
    {
        $definition = array(
            'WSDLAddress' => new ezcInputFormDefinitionElement(ezcInputFormDefinitionElement::OPTIONAL, 'unsafe_raw'),
            'WSDLUsername' => new ezcInputFormDefinitionElement(ezcInputFormDefinitionElement::OPTIONAL, 'unsafe_raw'),
            'WSDLPassword' => new ezcInputFormDefinitionElement(ezcInputFormDefinitionElement::OPTIONAL, 'unsafe_raw'),
            'SugarCRMEnabled' => new ezcInputFormDefinitionElement(ezcInputFormDefinitionElement::OPTIONAL, 'boolean'),
            'SugarCRMCreateFromOffline' => new ezcInputFormDefinitionElement(ezcInputFormDefinitionElement::OPTIONAL, 'boolean'),
            'SugarCRMLHCIdentifier' => new ezcInputFormDefinitionElement(ezcInputFormDefinitionElement::OPTIONAL, 'unsafe_raw', null, FILTER_REQUIRE_ARRAY),
            'SugarCRMLeadField' => new ezcInputFormDefinitionElement(ezcInputFormDefinitionElement::OPTIONAL, 'unsafe_raw', null, FILTER_REQUIRE_ARRAY)
        );
        
        $form = new ezcInputForm(INPUT_POST, $definition);
        
        $Errors = array();
        
        if (! $form->hasValidData('WSDLAddress') || $form->WSDLAddress == '') {
            $Errors[] = erTranslationClassLhTranslation::getInstance()->getTranslation('sugarcrm/module', 'Please enter SugarCRM WSDL address');
        } else {
            $settings['wsdl_address'] = $form->WSDLAddress;
        }
      
        if ($form->hasValidData('SugarCRMEnabled') && $form->SugarCRMEnabled == true) {
            $settings['sugarcrm_enabled'] = true;
        } else {
            $settings['sugarcrm_enabled'] = false;
        }
        
        if ($form->hasValidData('SugarCRMCreateFromOffline') && $form->SugarCRMCreateFromOffline == true) {
            $settings['sugarcrm_offline_lead'] = true;
        } else {
            $settings['sugarcrm_offline_lead'] = false;
        }
        
        if (! $form->hasValidData('WSDLUsername') || $form->WSDLUsername == '') {
            $Errors[] = erTranslationClassLhTranslation::getInstance()->getTranslation('sugarcrm/module', 'Please enter SugarCRM username');
        } else {
            $settings['wsdl_username'] = $form->WSDLUsername;
        }
        
        if (! $form->hasValidData('WSDLPassword') || $form->WSDLPassword == '') {
            if ($settings['wsdl_password'] == '') {
                $Errors[] = erTranslationClassLhTranslation::getInstance()->getTranslation('sugarcrm/module', 'Please enter SugarCRM password');
            }
        } else {
            if ($form->WSDLPassword != '') {
                $settings['wsdl_password'] = md5($form->WSDLPassword);
            }
        }
        
        if ($form->hasValidData('SugarCRMLHCIdentifier') && ! empty($form->SugarCRMLHCIdentifier)) {
            $fieldsData = array();
            
            foreach ($form->SugarCRMLHCIdentifier as $key => $lhcFieldIdentifier) {
                $fieldsData[] = array(
                    'lhcfield' => $lhcFieldIdentifier,
                    'sugarcrm' => $form->SugarCRMLeadField[$key]
                );
            }
            
            $settings['lead_extra_fields'] = $fieldsData;
        } else {
            $settings['lead_extra_fields'] = array();
        }
        
        return $Errors;
    }
}