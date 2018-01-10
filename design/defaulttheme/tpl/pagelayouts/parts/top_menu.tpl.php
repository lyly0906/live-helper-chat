<?php $currentUser = erLhcoreClassUser::instance(); ?>
<nav class="navbar navbar-default navbar-lhc" style="display:none;">
  <div class="container-fluid toggled">
    <!-- Brand and toggle get grouped for better mobile display -->
    <div class="navbar-header">
      <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#bs-example-navbar-collapse-1" >
        <span class="sr-only"><?php echo erTranslationClassLhTranslation::getInstance()->getTranslation('pagelayout/pagelayout','Menu');?></span>
        <i class="material-icons mr-0">menu</i>
      </button>
    
      <?php include_once(erLhcoreClassDesign::designtpl('pagelayouts/parts/page_head_logo_back_office.tpl.php'));?>
      
     <!-- <button type="button" class="navbar-toggle navbar-toggle-visible pull-left" ng-click="lhc.toggleList('lmtoggle')" title="<?php echo erTranslationClassLhTranslation::getInstance()->getTranslation('pagelayout/pagelayout','Expand or collapse left menu');?>">
        <span class="sr-only"></span>
        <i class="material-icons mr-0">menu</i>
      </button>-->
    </div>
      <div class="collapse navbar-collapse navbar-right" id="bs-example-navbar-collapse-1">    
          <ul class="nav navbar-nav navbar-inline">
             <li><a href="<?php echo erLhcoreClassDesign::baseurl('/')?>"><i class="material-icons md-18">home</i><?php echo erTranslationClassLhTranslation::getInstance()->getTranslation('pagelayout/pagelayout','Dashboard')?></a></li>
        	 <?php include(erLhcoreClassDesign::designtpl('pagelayouts/parts/top_menu_chat_actions_pre.tpl.php'));?>
             <?php include(erLhcoreClassDesign::designtpl('pagelayouts/parts/modules_menu/modules_permissions.tpl.php'));?>
              <li>
                  <a href="#" id="modules"><i class="material-icons">info_outline</i><?php include(erLhcoreClassDesign::designtpl('pagelayouts/parts/extra_modules_title.tpl.php'));?><i class="material-icons arrow md-18">chevron_right</i></a>
                  <ul class="nav nav-second-level" style="display: none;position:absolute;z-index:10;width:160px;background:#32be47;color:white;" id="modulelists">
                      <?php include(erLhcoreClassDesign::designtpl('pagelayouts/parts/modules_menu/questionary.tpl.php'));?>

                      <?php include(erLhcoreClassDesign::designtpl('pagelayouts/parts/modules_menu/faq.tpl.php'));?>

                      <?php include(erLhcoreClassDesign::designtpl('pagelayouts/parts/modules_menu/chatbox.tpl.php'));?>

                      <?php include(erLhcoreClassDesign::designtpl('pagelayouts/parts/modules_menu/browseoffer.tpl.php'));?>

                      <?php include(erLhcoreClassDesign::designtpl('pagelayouts/parts/modules_menu/form.tpl.php'));?>

                      <?php include(erLhcoreClassDesign::designtpl('pagelayouts/parts/modules_menu/extension_module_multiinclude.tpl.php'));?>
                  </ul>
              </li>

              <li>
                  <a href="#" id="chats"><i class="material-icons">chat</i>聊天<i class="material-icons arrow">chevron_right</i></a>
             <?php if ($parts_top_menu_chat_actions_enabled == true && $currentUser->hasAccessTo('lhchat','allowchattabs')) : ?>
              <ul class="nav nav-second-level" style="display: none;position:absolute;z-index:10;width:160px;background:#32be47;color:white;" id="chatlists">
                <li class="li-icon"><a title="<?php echo erTranslationClassLhTranslation::getInstance()->getTranslation('pagelayout/pagelayout','Chat tabs');?>" href="javascript:void(0)" onclick="javascript:lhinst.chatTabsOpen()"><i class="material-icons">chat</i><?php echo erTranslationClassLhTranslation::getInstance()->getTranslation('pagelayout/pagelayout','Chat tabs'); ?></a></li>
                <li class="li-icon"><a title="<?php echo erTranslationClassLhTranslation::getInstance()->getTranslation('pagelayout/pagelayout','Chats list');?>" href="<?php echo erLhcoreClassDesign::baseurl('chat/list'); ?>"><i class="material-icons">list</i><?php echo erTranslationClassLhTranslation::getInstance()->getTranslation('pagelayout/pagelayout','Chats list'); ?></a></li>
                <li class="li-icon"><a title="<?php echo erTranslationClassLhTranslation::getInstance()->getTranslation('pagelayout/pagelayout','Online visitors');?>" href="<?php echo erLhcoreClassDesign::baseurl('chat/onlineusers'); ?>"><i class="material-icons">face</i><?php echo erTranslationClassLhTranslation::getInstance()->getTranslation('pagelayout/pagelayout','Online visitors'); ?></a></li>
              </ul>
             <?php endif;?>
              </li>
              <script>
                  $(function(){
                      $('#modules').click(function(){
                          $('#modulelists').toggle();
                          $('#chatlists').hide();
                      });
                      $('#chats').click(function(){
                          $('#chatlists').toggle();
                          $('#modulelists').hide();
                      });
                  });
              </script>
        	 <?php if ($currentUser->hasAccessTo('lhsystem','use')) : ?>
               <li class="li-icon"><a title="<?php echo erTranslationClassLhTranslation::getInstance()->getTranslation('pagelayout/pagelayout','Configuration');?>" href="<?php echo erLhcoreClassDesign::baseurl('system/configuration')?>"><i class="material-icons">settings_applications</i></a></li>
             <?php endif; ?> 
                           
        	 <?php $hideULSetting = true;?>
    		 <?php include(erLhcoreClassDesign::designtpl('lhchat/user_settings.tpl.php'));?>
          </ul>
          <ul class="nav navbar-nav">  
              <?php include_once(erLhcoreClassDesign::designtpl('pagelayouts/parts/user_box.tpl.php'));?> 
          </ul>
      </div>
    </div>
</nav>