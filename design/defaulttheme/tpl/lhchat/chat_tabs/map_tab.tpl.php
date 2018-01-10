<?php include(erLhcoreClassDesign::designtpl('lhchat/chat_tabs/map_tab_pre.tpl.php'));?>

<?php if ($information_tab_map_tab_enabled == true) : ?>
<div role="tabpanel" class="tab-pane<?php if ($chatTabsOrderDefault == 'map_tab_tab') print ' active';?>" id="map-tab-chat-<?php echo $chat->id?>">
        <?php if ($chat->lat != 0 && $chat->lon) : ?>
			<iframe src="http://livechat.wisehub.cn/map.html?lat=<?php echo $chat->lat;?>&lon=<?php echo $chat->lon;?>" width="100%" height="400px" frameborder="0"></iframe>

		<?php else : ?>
		<p><?php echo erTranslationClassLhTranslation::getInstance()->getTranslation('chat/adminchat','Could not detect. Make sure that GEO detection is enabled.')?></p>
		<?php endif;?>
</div>

<?php endif;?>
