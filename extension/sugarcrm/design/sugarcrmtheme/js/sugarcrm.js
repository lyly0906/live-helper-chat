var sugarcrm = {
		createOrUpdate : function(btn,chat_id) {
			var $btn = btn.button('loading');
			console.log(WWW_DIR_JAVASCRIPT + 'sugarcrm/createorupdatelead/' + chat_id);
			$.postJSON(WWW_DIR_JAVASCRIPT + 'sugarcrm/createorupdatelead/' + chat_id, function(data){
				$('#sugar-crm-lead-info-'+chat_id).html(data.result);
				$btn.button('reset');
				
				if (data.error == false) {
					sugarcrm.loadLead(data.lead_id,chat_id);
				}
			});
		},	
		loadLead : function(lead_id,chat_id) {
			if (lead_id != '') {
				if ($('#'+lead_id).html() == '') {
					console.log(WWW_DIR_JAVASCRIPT + 'sugarcrm/getleadfields/' + chat_id);
					$.getJSON(WWW_DIR_JAVASCRIPT + 'sugarcrm/getleadfields/' + chat_id, function(data){
						$('#'+lead_id).html(data.result);
					});
				}
			}
		},	
		updateLeadFields : function(lead_id,chat_id,form) {
			if (lead_id != '') {				
				var $btn = $('.btn-sugarcrm').button('loading');				
				$.postJSON(WWW_DIR_JAVASCRIPT + 'sugarcrm/updateleadfields/' + chat_id, form.serialize(), function(data){
					$('#'+lead_id).html(data.result);
					$btn.button('reset');
				});
			}
			return false;
		}
};