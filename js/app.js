jQuery(document).ready(function ($) {
  $("#rutinas-poderosas-trigger").fancybox().trigger("click");
  flatpickr(".datepicker", {
    maxDate: "today",
  });
  // Initialize intlTelPhone
  var input = document.querySelector(".phoneNumber");
  var iti = window.intlTelInput(input);


  $("#rutinas-popup").submit(function (e) {
    e.preventDefault();
    var form = $(this).serializeArray();
    var post = {};

    // Convert to json object
    form.forEach(function (field) {
      post[field.name] = field.value;
    });
    var selectedCountry = iti.getSelectedCountryData();
    post.telefono = "+" + selectedCountry.dialCode + post.telefono;
    post.action = "RPP_register";

    // Send ajax
    $("#rutinas-popup #obtener").prop("disabled", true).text("Enviando E-Book...");
    $.ajax({
      url: RPP_variables.ajaxurl,
      method: "POST",
      data: post,
      success: function (data) {
        $.fancybox.close();
      },
      error: function () {
        $.fancybox.close();
      },
    });
  });
});
