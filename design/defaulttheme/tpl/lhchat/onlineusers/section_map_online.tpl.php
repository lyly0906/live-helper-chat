<?php include(erLhcoreClassDesign::designtpl('lhchat/onlineusers/section_map_online_pre.tpl.php')); ?>
<?php if ($chat_onlineusers_section_map_online_enabled == true) : ?>
<div class="row form-group">
	<div class="col-xs-6">
		<img data-toggle="tooltip" data-placement="bottom" class="tip-right" title="<?php echo erTranslationClassLhTranslation::getInstance()->getTranslation('chat/onlineusers','User is chatting');?>" src="<?php echo erLhcoreClassDesign::design('images/icons/home-chat.png')?>" />
		<img data-toggle="tooltip" data-placement="bottom" class="tip-right" title="<?php echo erTranslationClassLhTranslation::getInstance()->getTranslation('chat/onlineusers','User does not have any message from operator');?>" src="<?php echo erLhcoreClassDesign::design('images/icons/home-unsend.png')?>" />
		<img data-toggle="tooltip" data-placement="bottom" class="tip-right" title="<?php echo erTranslationClassLhTranslation::getInstance()->getTranslation('chat/onlineusers','User has message from operator');?>" src="<?php echo erLhcoreClassDesign::design('images/icons/home-send.png')?>" />
	</div>
	<div class="col-xs-3">
	<?php echo erLhcoreClassRenderHelper::renderCombobox( array (
                    'input_name'     => 'department_map_id',
					'optional_field' => erTranslationClassLhTranslation::getInstance()->getTranslation('chat/lists/search_panel','Select department'),
                    'selected_id'    => $omapDepartment,
	                'css_class'      => 'form-control input-sm',
                    'list_function'  => 'erLhcoreClassModelDepartament::getList'
    )); ?>
    </div>
	<div class="col-xs-3">
		<select class="form-control input-sm" id="markerTimeout" title="<?php echo erTranslationClassLhTranslation::getInstance()->getTranslation('chat/onlineusers','Marker timeout before it dissapears from map');?>">
			<option value="30" <?php echo $omapMarkerTimeout == 30 ? 'selected="selected"' : ''?> >30 <?php echo erTranslationClassLhTranslation::getInstance()->getTranslation('chat/onlineusers','seconds');?></option>
			<option value="60" <?php echo $omapMarkerTimeout == 60 ? 'selected="selected"' : ''?> >1 <?php echo erTranslationClassLhTranslation::getInstance()->getTranslation('chat/onlineusers','minute');?></option>
			<option value="120" <?php echo $omapMarkerTimeout == 120 ? 'selected="selected"' : ''?> >2 <?php echo erTranslationClassLhTranslation::getInstance()->getTranslation('chat/onlineusers','minutes');?></option>
			<option value="300" <?php echo $omapMarkerTimeout == 300 ? 'selected="selected"' : ''?> >5 <?php echo erTranslationClassLhTranslation::getInstance()->getTranslation('chat/onlineusers','minutes');?></option>
			<option value="600" <?php echo $omapMarkerTimeout == 600 ? 'selected="selected"' : ''?> >10 <?php echo erTranslationClassLhTranslation::getInstance()->getTranslation('chat/onlineusers','minutes');?></option>
			</select>
		</div>
	</div>

<style type="text/css">

	#gdmap_canvas{
		width: 100%;
		height: 600px;
		margin: 0px
	}
</style>
<div id="gdmap_canvas" tabindex="0"></div>
<script src="https://webapi.amap.com/maps?v=1.3&key=51a56d9b6e257867404b06f173175135"></script>
<script type="text/javascript">
    var map = new AMap.Map('gdmap_canvas', {
        resizeEnable: true,
        center:[108.928596,34.2583007],
        zoom:14
    });

    var locationSet = false;

    var processing = false;
    var pendingProcess = false;
    var pendingProcessTimeout = false;

    AMap.event.addListener(map, 'idle', showMarkers);

    var mapTabSection = $('#map-activator').parent();

    function showMarkers() {
        if ( processing == false) {
            if (mapTabSection.hasClass('active')) {
                processing = true;
                $.ajax({
                    url : WWW_DIR_JAVASCRIPT + 'chat/jsononlineusers'+(parseInt($('#id_department_map_id').val()) > 0 ? '/(department)/'+parseInt($('#id_department_map_id').val()) : '' )+(parseInt($('#maxRows').val()) > 0 ? '/(maxrows)/'+parseInt($('#maxRows').val()) : '' )+(parseInt($('#userTimeout').val()) > 0 ? '/(timeout)/'+parseInt($('#userTimeout').val()) : '' ),
                    dataType: "json",
                    error:function(){
                        clearTimeout(pendingProcessTimeout);
                        pendingProcessTimeout = setTimeout(function(){
                            showMarkers();
                        },10000);
                    },
                    success : function(response) {
                        console.log(response);
                        bindMarkers(response);
                        processing = false;
                        clearTimeout(pendingProcessTimeout);
                        if (pendingProcess == true) {
                            pendingProcess = false;
                            showMarkers();
                        } else {
                            pendingProcessTimeout = setTimeout(function(){
                                showMarkers();
                            },10000);
                        }
                    }
                });
            } else {
                pendingProcessTimeout = setTimeout(function(){
                    showMarkers();
                },10000);
            }
        } else {
            pendingProcess = true;
        }
    };

    var markers = [];
    var markersObjects = [];

    //var infoWindow = new google.maps.InfoWindow({ content: 'Loading...' });

    function bindMarkers(mapData) {
        console.log(mapData.result);
        $(mapData.result).each(function(i, e) {
            if ($.inArray(e.Id,markers) == -1) {
                var latLng = new AMap.LngLat(e.Longitude,e.Latitude);
                console.log(latLng);
                var marker = new AMap.Marker({
                    icon: e.icon,
                    position: latLng
                });
                marker.setAnimation("AMAP_ANIMATION_BOUNCE");
                marker.setMap(map);
                AMap.event.addListener(marker, 'click', function() {
                    lhc.revealModal({'url':WWW_DIR_JAVASCRIPT+'chat/getonlineuserinfo/'+e.Id})
                });
                console.log(marker);
                markersObjects[e.Id] = marker;
                markers.push(e.Id);
                clearTimeout(markersObjects[e.Id].timeOutMarker);

                markersObjects[e.Id].timeOutMarker = setTimeout(function(){
                    markers.splice($.inArray(e.Id,markers), 1);
                    AMap.event.removeListener(markersObjects[e.Id]);
                    markersObjects[e.Id].setMap(null);
                    markersObjects[e.Id] = null;
                },parseInt($('#markerTimeout option:selected').val())*1000);

            } else {
                markersObjects[e.Id].setIcon(e.icon);
                clearTimeout(markersObjects[e.Id].timeOutMarker);
                markersObjects[e.Id].timeOutMarker = setTimeout(function(){
                    markers.splice($.inArray(e.Id,markers), 1);
                    AMap.event.removeListener(markersObjects[e.Id]);
                    markersObjects[e.Id].setMap(null);
                    markersObjects[e.Id] = null;
                },parseInt($('#markerTimeout option:selected').val())*1000);
            }
        });
    };

    $('#id_department_map_id').change(function(){
        showMarkers();
        lhinst.changeUserSettingsIndifferent('omap_depid',$(this).val());
    });

    $('#markerTimeout').change(function(){
        showMarkers();
        lhinst.changeUserSettingsIndifferent('omap_mtimeout',$(this).val());
    });

    $('#map-activator').click(function(){
        setTimeout(function(){
            // google.maps.event.trigger(map, 'resize');
            if (locationSet == false) {
                locationSet = true;
                map.setCenter(108.928596,34.2583007);
            }
        },500);
        showMarkers();
    });


</script>
<script type="text/javascript" src="https://webapi.amap.com/demos/js/liteToolbar.js"></script>
<?php endif;?>
