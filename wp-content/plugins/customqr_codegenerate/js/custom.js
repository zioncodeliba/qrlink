jQuery(function ($) {

  function defaltValueShow(para){
  
    if (para === 'External') {
      $("#products_fields").hide();
      $("#urlExternal").show();
      $("#urlInternal").hide();
    } else {
      $("#urlInternal").show();
      showAttr();
      $("#urlExternal").hide();
    }
  }

  
  function showAttr(){
    var selectedValue = jQuery('#int_url').val();
    var targetField = `[data-value = ${selectedValue}]`;
    jQuery('[data-value]').each(function () {
    jQuery(this).css({display:'none'});
    jQuery(targetField).css({display:'block'})
    });
  }

  var defaultSelected = $('input[name="url_type"]:checked').val();
  defaltValueShow(defaultSelected);

  $('input[name="url_type"]').change(function () {
    newAttrShow = $(this).val();
    defaltValueShow(newAttrShow);
  });

  $('#int_url').on('change', function () {
    showAttr();
  });


  $('#product_discount').click(function () {
    if ($("#product_discount").is(':checked')) {
      $("#discount_fields_type").show();
      $("#discount_fields_value").show();
      $("#discount_fields_coupon").show();
    } else {
      $("#discount_fields_type").hide();
      $("#discount_fields_value").hide();
      $("#discount_fields_coupon").hide();
    }
  });

  if ($("#product_discount").is(':checked')) {
    $("#discount_fields_type").show();
    $("#discount_fields_value").show();
    $("#discount_fields_coupon").show();
  } else {
    $("#discount_fields_type").hide();
    $("#discount_fields_value").hide();
    $("#discount_fields_coupon").hide();
  }

  var selectedOption = jQuery("#int_url").val();
  var showSelect = `[data-value = ${selectedOption}]`;
  jQuery(showSelect).css({display:'block'});

});

// jQuery('[name="url_type"]').change(function(){
//   showAttr();
// });