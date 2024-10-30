(function($){
$(document).ready(function()
{
	function get_toggle_id(el) 
	{ 
		return '#' + $(el).attr('id').replace('toggle-',''); 
	}
	$('#toggle-button-options').toggle(
		function()
		{
			$(this).html('Click here to hide');
			$(get_toggle_id(this)).css('display','block');
		},
		function()
		{
			$(this).html('Click here to show');
			$(get_toggle_id(this)).css('display','none');
		}
	);

	function turnCustomOn()
	{
		$('#contribute_custom').val(1);
	}
	function turnCustomOff()
	{
		$('#contribute_custom').val(0);
	}

	var color_picker_id = null;
	$('#contribute_fgcolor, #contribute_brcolor, #contribute_bgcolor').ColorPicker(
	{
		onSubmit: function(hsb, hex, rgb, el)
		{
			$(el).ColorPickerHide();
			$('#'+color_picker_id).val('#'+hex);
			turnCustomOn();
		},
		onBeforeShow: function ()
		{
			var value = this.value.replace('#','');
			$(this).ColorPickerSetColor(value);

			color_picker_id = $(this).attr('id');
		},
		onChange : function(hsb, hex, rgb)
		{
			$('#'+color_picker_id).val('#'+hex);
			turnCustomOn();
		}
	});

	$('#button-options').find('input,select').each(function()
	{
		if($(this).is('input'))
		{
			switch( $(this).attr('type') )
			{
				case 'text':
					$(this).keyup(turnCustomOn);
					break;
				case 'checkbox':
					$(this).click(turnCustomOn);
					break;
			}
		}
		else if($(this).is('select'))
		{
			$(this).change(turnCustomOn);
		}
	});

	$('#contribute_restore').click(function()
	{
		turnCustomOff();
		// We are doing this, because JQuery is so stupid that
		// it just won't submit the form if you have an input
		// with the name or id set to submit !!! What a shame?!
		$('#submit').click();
	});


});})(jQuery);
