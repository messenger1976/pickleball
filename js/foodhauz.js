var cartCounter=0;

function Cart(){
    this.countcart=getcountCart;
}

function updateCartCountDisplay(count) {
    var n = parseInt(count, 10);
    if (isNaN(n) || n < 0) {
        n = 0;
    }
    $('.js-cart-count').text(n);
    $('.topnav-cart-badge').toggleClass('is-empty', n <= 0);
}

function updateNavProfileAvatar(url) {
    var $profile = $('.topnav-profile');
    if (!$profile.length) {
        return;
    }

    var $img = $('#topnavProfileAvatar');
    var $initials = $('#topnavProfileInitials');

    if (url) {
        var bustUrl = url + (url.indexOf('?') >= 0 ? '&' : '?') + 't=' + Date.now();
        $img.attr('src', bustUrl).show();
        $initials.hide();
        $profile.addClass('has-avatar').removeClass('has-initials');
        return;
    }

    $img.hide().attr('src', '');
    $initials.show();
    $profile.addClass('has-initials').removeClass('has-avatar');
}

function getcountCart(){
    $.ajax({
           type: "POST",
           url: "process.php",
           data: {
           			'function': 'getcountCart'
            },
           dataType: "json",
           success: function(return_data){
                updateCartCountDisplay(return_data.size_of_cart);
           }
     });
}

function validateEmail(email) {
    const re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
    return re.test(String(email).toLowerCase());
}

function validatePhone(phone) {
    var digits = String(phone || '').replace(/\D/g, '');
    return digits.length >= 10 && digits.length <= 13;
}

function openNav() {
    var el = document.getElementById("mySidenav");
    if (!el) {
        return;
    }
    el.classList.add("is-open");
}

function closeNav() {
    var el = document.getElementById("mySidenav");
    if (!el) {
        return;
    }
    el.classList.remove("is-open");
}

function initSidenav() {
    var el = document.getElementById("mySidenav");
    if (!el) {
        return;
    }
    el.addEventListener("click", function(e) {
        if (e.target === el) {
            closeNav();
        }
    });
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initSidenav);
} else {
    initSidenav();
}

function initPortalTopnav() {
    var nav = document.querySelector('.topnav');
    if (!nav) {
        return;
    }

    function updateTopnavScroll() {
        if (window.pageYOffset > 8) {
            nav.classList.add('is-scrolled');
        } else {
            nav.classList.remove('is-scrolled');
        }
    }

    window.addEventListener('scroll', updateTopnavScroll, false);
    updateTopnavScroll();
}

$(document).ready(function() {
    initPortalTopnav();
    cart.countcart();

    $(document).ajaxStart(function(){
        $("#overlay").css("display", "block");
    });
    $(document).ajaxComplete(function(){
        $("#overlay").css("display", "none");
    });

    $("#btnConfirm").click(function(){
        $("#checkout").trigger('submit');
    });

    $("#frmRegister").submit(function(event){
        event.preventDefault();

        if (!runRegisterValidation(true)) {
            return false;
        }

        var email = $("#email").val().trim();
        var psw = $("#psw").val();
        var $btn = $("#registerbtn");

        $btn.prop('disabled', true).text('Creating account…');

        $.ajax({
            type: "POST",
            url: "process.php",
            data: {
                'function':'SignUp',
                email : email,
                psw:psw
            },
            dataType:"json",
            success: function(return_data){
                $btn.prop('disabled', false).text('Create Account');

                if(return_data.validation_status=='T'){
                  window.location.href = return_data.msg;
                  return;
                }

                showPanelAlert('#registerMsgbox', return_data.msg || 'Unable to create account. Please try again.');
            },
            error: function() {
                $btn.prop('disabled', false).text('Create Account');
                showPanelAlert('#registerMsgbox', 'Something went wrong. Please check your connection and try again.');
            }
        });

        return false;
    });

    $('#email, #psw, #psw-repeat').on('input', function() {
        clearFieldError($(this).attr('id'));
        $('#registerMsgbox').fadeOut('fast');
    });

    $('#btnSubmitSMS').on("click",function(){
        $("#overlay").css("display", "block");

    })
    $('#smsformID').on("submit",function(event){
     $("#overlay").css("display", "block");
     $('iframe').load(function() {
            $("#overlay").css("display", "block");
            window.location.href = 'thankyou.php';;
            return;
        });
    })

    $("#checkout").submit(function(event){
        event.preventDefault();

        if (!runCheckoutValidation(true)) {
            return false;
        }

        var selectedCompany = '0';
        var selectedDepartment = '0';
        var email = $("#email").val().trim();
        var fname = $("#fname").val().trim();
        var lname = $("#lname").val().trim();
        var address = $("#address").val().trim();
        var country = getCheckoutCountry();
        var province = getCheckoutProvince();
        var town = getCheckoutTown();
        var barangay = getCheckoutBarangay();
        var zipcode = $('#zipcode').length ? String($('#zipcode').val() || '').trim() : '';
        var contact = $("#contact").val().trim();
        var customer_id = $("#customer_id").val();
        var payment = $("input[name='payment']:checked").val();
        var shipping = $("input[name='shipping']:checked").val();
        var remarks = $("#remarks").val().trim();
        var shipping_date = getPreorderShippingDate();
        var shipping_time = getPreorderShippingTime();
        var dinein_table = getDineinTable();
        var $btn = $("#btnConfirm");

        $btn.prop('disabled', true).text('Placing order…');
        $('#checkoutMsgbox').hide().empty();

        $.ajax({
            type: "POST",
            url: "process.php",
            data: {
                'function':'confirmCheckout',
                customer_id : customer_id,
                company:selectedCompany,
                department:selectedDepartment,
                email: email,
                fname: fname,
                lname:lname,
                address:address,
                country: country,
                province: province,
                town: town,
                barangay: barangay,
                zipcode: zipcode,
                contact: contact,
                payment: payment,
                shipping: shipping,
                remarks: remarks,
                shipping_date: shipping_date,
                shipping_time: shipping_time,
                dinein_table: dinein_table
            },
            dataType:"json",
            success: function(return_data){
                $btn.prop('disabled', false).text('Confirm');

                if(return_data.validation_status=='T'){
                    $("#frm_senders_namex").val(fname+" "+lname);
                    $("#frm_senders_num").val(contact);
                    $("#frm_recepient_num").val("09452135338");
                    var smsmsg = '\nRECEIPT NO.:'+(return_data.receipt_no || return_data.msg)+'\nORDER NO.:'+return_data.msg+'\nORDER DATE:'+return_data.menudate+'\nNAME:'+return_data.fullname+'\nFOOD:'+return_data.smsbody+'\nTOTAL:'+return_data.grandtotal+'\nREMARKS:'+return_data.remarks;
                    $("#frm_messagex").val(smsmsg);
                    $("#btnConfirm").hide();
                    window.location.href = "thankyou.php";
                    return;
                }

                showPanelAlert('#checkoutMsgbox', return_data.msg || 'Unable to place your order. Please check your details and try again.');
            },
            error: function() {
                $btn.prop('disabled', false).text('Confirm');
                showPanelAlert('#checkoutMsgbox', 'Something went wrong. Please check your connection and try again.');
            }
        });

        return false;
    });

    $('#fname, #lname, #email, #address, #contact, #province_text, #town_text, #barangay_text').on('input', function() {
        var id = $(this).attr('id');
        if (id === 'province_text') id = 'province';
        else if (id === 'town_text') id = 'town';
        else if (id === 'barangay_text') id = 'barangay';
        clearFieldError(id);
        $('#checkoutMsgbox').fadeOut('fast');
    });

    $('#country, #province, #town, #barangay').on('change', function() {
        var id = $(this).attr('id');
        clearFieldError(id);
        $('#checkoutMsgbox').fadeOut('fast');
    });

    function getCheckoutCountry() {
        return $('#country').length ? String($('#country').val() || '').trim() : '';
    }

    function getCheckoutProvince() {
        if ($('#province_text').is(':visible')) {
            return String($('#province_text').val() || '').trim();
        }
        return String($('#province').val() || '').trim();
    }

    function getCheckoutTown() {
        if ($('#town_text').is(':visible')) {
            return String($('#town_text').val() || '').trim();
        }
        return String($('#town').val() || '').trim();
    }

    function getCheckoutBarangay() {
        if ($('#barangay_text').is(':visible')) {
            return String($('#barangay_text').val() || '').trim();
        }
        return String($('#barangay').val() || '').trim();
    }

    function setBarangayMode(hasList) {
        if (!$('#barangay').length) {
            return;
        }
        if (hasList) {
            $('#barangay').show();
            $('#barangay_text').hide().val('');
        } else {
            $('#barangay').hide().html('<option value="">Select barangay</option>');
            $('#barangay_text').show();
        }
    }

    function setCheckoutLocationMode(isPhilippines) {
        if (isPhilippines) {
            $('#province').show();
            $('#town').show();
            $('#province_text').hide().val('');
            $('#town_text').hide().val('');
        } else {
            $('#province').hide();
            $('#town').hide();
            $('#province_text').show();
            $('#town_text').show();
            setBarangayMode(false);
        }
    }

    function loadCheckoutProvinces(countryId, selectedProvince) {
        var $province = $('#province');
        var $town = $('#town');
        if (!$province.length) {
            return;
        }
        $province.prop('disabled', true).html('<option value="">Loading...</option>');
        $town.html('<option value="">Select town / city</option>');
        setBarangayMode(false);
        $.ajax({
            type: 'POST',
            url: 'process.php',
            data: {
                'function': 'getProvinces',
                country_id: countryId
            },
            dataType: 'json',
            success: function(data) {
                var count = parseInt(data.count, 10) || 0;
                setCheckoutLocationMode(count > 0);
                if (count > 0) {
                    $province.html(data.msg).prop('disabled', false);
                    if (selectedProvince) {
                        $province.val(selectedProvince);
                        var provinceId = $province.find('option:selected').data('id');
                        if (provinceId) {
                            loadCheckoutTowns(provinceId);
                        }
                    }
                } else {
                    $province.html('<option value="">Select province</option>').prop('disabled', false);
                }
            },
            error: function() {
                $province.html('<option value="">Select province</option>').prop('disabled', false);
                setCheckoutLocationMode(false);
            }
        });
    }

    function loadCheckoutTowns(provinceId, selectedTown, selectedBarangay) {
        var $town = $('#town');
        if (!$town.length) {
            return;
        }
        $town.prop('disabled', true).html('<option value="">Loading...</option>');
        setBarangayMode(false);
        $.ajax({
            type: 'POST',
            url: 'process.php',
            data: {
                'function': 'getTowns',
                province_id: provinceId
            },
            dataType: 'json',
            success: function(data) {
                $town.html(data.msg).prop('disabled', false);
                if (selectedTown) {
                    $town.val(selectedTown);
                }
                fillCheckoutZipcodeFromTown();
                var townId = $town.find('option:selected').data('id');
                if (townId) {
                    loadCheckoutBarangays(townId, selectedBarangay);
                } else {
                    setBarangayMode(false);
                }
            },
            error: function() {
                $town.html('<option value="">Select town / city</option>').prop('disabled', false);
                setBarangayMode(false);
            }
        });
    }

    function loadCheckoutBarangays(townId, selectedBarangay) {
        var $barangay = $('#barangay');
        if (!$barangay.length) {
            return;
        }
        $barangay.prop('disabled', true).html('<option value="">Loading...</option>');
        $.ajax({
            type: 'POST',
            url: 'process.php',
            data: {
                'function': 'getBarangays',
                town_id: townId
            },
            dataType: 'json',
            success: function(data) {
                var count = parseInt(data.count, 10) || 0;
                if (count > 0) {
                    $barangay.html(data.msg).prop('disabled', false);
                    setBarangayMode(true);
                    if (selectedBarangay) {
                        $barangay.val(selectedBarangay);
                    }
                } else {
                    $barangay.prop('disabled', false);
                    setBarangayMode(false);
                }
            },
            error: function() {
                $barangay.prop('disabled', false);
                setBarangayMode(false);
            }
        });
    }

    $('#country').on('change', function() {
        var countryId = $(this).find('option:selected').data('id') || 0;
        var countryName = $(this).val();
        var phId = parseInt($(this).data('ph-id'), 10) || 0;
        if (countryName === 'Philippines' || parseInt(countryId, 10) === phId) {
            setCheckoutLocationMode(true);
            loadCheckoutProvinces(countryId || phId);
        } else {
            setCheckoutLocationMode(false);
            $('#province').html('<option value="">Select province</option>');
            $('#town').html('<option value="">Select town / city</option>');
            setBarangayMode(false);
        }
    });

    function fillCheckoutZipcodeFromTown() {
        if (!$('#zipcode').length) {
            return;
        }
        var zip = '';
        if ($('#town').is(':visible')) {
            zip = String($('#town option:selected').data('zipcode') || '');
        }
        if (zip) {
            $('#zipcode').val(zip);
        }
    }

    $('#town').on('change', function() {
        clearFieldError('town');
        clearFieldError('barangay');
        $('#checkoutMsgbox').fadeOut('fast');
        fillCheckoutZipcodeFromTown();
        var townId = $(this).find('option:selected').data('id');
        if (townId) {
            loadCheckoutBarangays(townId);
        } else {
            setBarangayMode(false);
        }
    });

    $('#province').on('change', function() {
        var provinceId = $(this).find('option:selected').data('id') || 0;
        clearFieldError('province');
        $('#checkoutMsgbox').fadeOut('fast');
        if (provinceId) {
            loadCheckoutTowns(provinceId);
        } else {
            $('#town').html('<option value="">Select town / city</option>');
            $('#zipcode').val('');
            setBarangayMode(false);
        }
    });

    window.getCheckoutCountry = getCheckoutCountry;
    window.getCheckoutProvince = getCheckoutProvince;
    window.getCheckoutTown = getCheckoutTown;
    window.getCheckoutBarangay = getCheckoutBarangay;
    window.setCheckoutLocationMode = setCheckoutLocationMode;
    window.setBarangayMode = setBarangayMode;
    window.loadCheckoutProvinces = loadCheckoutProvinces;
    window.loadCheckoutTowns = loadCheckoutTowns;
    window.loadCheckoutBarangays = loadCheckoutBarangays;

    $("input[name='shipping']").on('change', function() {
        clearFieldError('address');
        clearFieldError('shipping');
        clearFieldError('shipping_date');
        clearFieldError('shipping_time');
        clearFieldError('dinein_table');
        $('#checkoutMsgbox').fadeOut('fast');
        updateCheckoutAddressRequirement();
        updateShippingMethodLabels();
        updateDineinTableVisibility();
    });

    $('#dinein_table').on('input', function() {
        clearFieldError('dinein_table');
        $('#checkoutMsgbox').fadeOut('fast');
    });

    $("input[name='payment']").on('change', function() {
        clearFieldError('payment');
        $('#checkoutMsgbox').fadeOut('fast');
    });

    $('#shipping_date, #shipping_time').on('change input blur', function() {
        clearFieldError($(this).attr('id'));
        $('#checkoutMsgbox').fadeOut('fast');
    });

    function getPreorderShippingDate() {
        var raw = $('#shipping_date').val();
        return raw && /^\d{4}-\d{2}-\d{2}$/.test(raw) ? raw : (raw || '');
    }

    function getPreorderShippingTime() {
        var raw = $('#shipping_time').val();
        return raw && /^\d{2}:\d{2}$/.test(raw) ? raw : (raw || '');
    }

    function getDineinTable() {
        if ($("input[name='shipping']:checked").val() !== 'dinein') {
            return '';
        }
        return String($('#dinein_table').val() || '').trim();
    }

    function updateDineinTableVisibility() {
        var isDinein = $("input[name='shipping']:checked").val() === 'dinein';
        var $wrap = $('#dinein_table_wrap');
        if (!$wrap.length) {
            return;
        }
        if (isDinein) {
            $wrap.removeAttr('hidden').addClass('is-visible');
        } else {
            $wrap.attr('hidden', 'hidden').removeClass('is-visible');
            $('#dinein_table').val('');
            clearFieldError('dinein_table');
        }
    }

    function shippingMethodLabel(shipping) {
        if (shipping === 'pickup') {
            return 'Pick-up';
        }
        if (shipping === 'dinein') {
            return 'Dine-in';
        }
        return 'Delivery';
    }

    function updateShippingMethodLabels() {
        if (!$('#shipping_date_label').length) {
            return;
        }
        var label = shippingMethodLabel($("input[name='shipping']:checked").val());
        $('#shipping_date_label').text(label + ' Date');
        $('#shipping_time_label').text(label + ' Time');
    }

    function updateCheckoutAddressRequirement() {
        var isDelivery = $("input[name='shipping']:checked").val() === 'delivery';
        $('.portal-checkout-required-delivery').toggle(isDelivery);
    }

    function clearCheckoutErrors() {
        clearFieldError('fname');
        clearFieldError('lname');
        clearFieldError('email');
        clearFieldError('address');
        clearFieldError('country');
        clearFieldError('province');
        clearFieldError('town');
        clearFieldError('barangay');
        clearFieldError('contact');
        clearFieldError('payment');
        clearFieldError('shipping');
        clearFieldError('shipping_date');
        clearFieldError('shipping_time');
        clearFieldError('dinein_table');
        $('#checkoutMsgbox').hide().empty();
    }

    function runCheckoutValidation(showAlerts) {
        clearCheckoutErrors();

        var isGuest = window.fhCheckoutGuest === true || parseInt($('#customer_id').val(), 10) === 0;
        var email = $('#email').val().trim();
        var fname = $('#fname').val().trim();
        var lname = $('#lname').val().trim();
        var address = $('#address').val().trim();
        var country = getCheckoutCountry();
        var province = getCheckoutProvince();
        var town = getCheckoutTown();
        var barangay = getCheckoutBarangay();
        var contact = $('#contact').val().trim();
        var payment = $("input[name='payment']:checked").val();
        var shipping = $("input[name='shipping']:checked").val();
        var hasError = false;
        var firstErrorField = null;

        function markError(fieldId, message) {
            showFieldError(fieldId, message);
            if (!firstErrorField) {
                firstErrorField = fieldId;
            }
            hasError = true;
        }

        if (fname === '') {
            markError('fname', 'First name is required.');
        }
        if (lname === '') {
            markError('lname', 'Last name is required.');
        }
        if (isGuest || email !== '') {
            if (email === '') {
                markError('email', 'Email is required.');
            } else if (!validateEmail(email)) {
                markError('email', 'Please enter a valid email address.');
            }
        }
        if (contact === '') {
            markError('contact', 'Contact number is required.');
        } else if (!validatePhone(contact)) {
            markError('contact', 'Enter a valid mobile number (at least 10 digits).');
        }
        if (country === '') {
            markError('country', 'Country is required.');
        }
        if (province === '') {
            markError('province', 'Province is required.');
        }
        if (town === '') {
            markError('town', 'Town / City is required.');
        }
        if (barangay === '') {
            markError('barangay', 'Barangay is required.');
        }
        if (shipping === 'delivery' && address === '') {
            markError('address', 'Delivery address is required for delivery orders.');
        }
        if (!payment) {
            markError('payment', 'Please select a payment method.');
        }
        if (!shipping) {
            markError('shipping', 'Please select a shipping method.');
        }

        if ($('#shipping_date').length) {
            var shippingDate = getPreorderShippingDate();
            var shippingTime = getPreorderShippingTime();
            var methodLabel = shippingMethodLabel(shipping);
            if (!shippingDate) {
                markError('shipping_date', methodLabel + ' date is required.');
            }
            if (!shippingTime) {
                markError('shipping_time', methodLabel + ' time is required.');
            }
        }
        if (shipping === 'dinein') {
            var dineinTable = getDineinTable();
            if (dineinTable === '') {
                markError('dinein_table', 'Table number is required for dine-in orders.');
            }
        }

        if (hasError && showAlerts) {
            showPanelAlert('#checkoutMsgbox', 'Please fix the highlighted fields below.');
            if (firstErrorField) {
                var $field = $('#' + firstErrorField);
                if ($field.length && $field.is(':hidden')) {
                    if (firstErrorField === 'province') {
                        $field = $('#province_text').is(':visible') ? $('#province_text') : $field;
                    } else if (firstErrorField === 'town') {
                        $field = $('#town_text').is(':visible') ? $('#town_text') : $field;
                    }
                    if (firstErrorField === 'barangay') {
                        $field = $('#barangay_text').is(':visible') ? $('#barangay_text') : $field;
                    }
                }
                if ($field.length) {
                    $('html, body').animate({
                        scrollTop: $field.offset().top - 100
                    }, 300);
                    $field.focus();
                }
            }
        }

        return !hasError;
    }

    if ($('#checkout').length) {
        updateCheckoutAddressRequirement();
        updateShippingMethodLabels();
        updateDineinTableVisibility();
        if ($('#country').length) {
            setCheckoutLocationMode($('#country').val() === 'Philippines');
        }
    }
    $("#ProfileForm").submit(function(event) {
        event.preventDefault();

        if (!runProfileValidation(true)) {
            return false;
        }

        var fname = $("#fname").val().trim();
        var lname = $("#lname").val().trim();
        var address = $("#address").val().trim();
        var country = typeof getCheckoutCountry === 'function' ? getCheckoutCountry() : ($('#country').val() || '');
        var province = typeof getCheckoutProvince === 'function' ? getCheckoutProvince() : ($('#province').val() || '');
        var town = typeof getCheckoutTown === 'function' ? getCheckoutTown() : ($('#town').val() || '');
        var barangay = typeof getCheckoutBarangay === 'function' ? getCheckoutBarangay() : ($('#barangay').val() || '');
        var zipcode = $('#zipcode').length ? String($('#zipcode').val() || '').trim() : '';
        var contact = $("#contact").val().trim();
        var customer_id = $("#customer_id").val();
        var $btn = $('#btnSaveProfile');

        $btn.prop('disabled', true).text('Saving…');

        $.ajax({
            type: "POST",
            url: "process.php",
            data: {
                'function':'saveProfile',
                customer_id : customer_id,
                company: 0,
                department: 0,
                fname: fname,
                lname:lname,
                address:address,
                country: country,
                province: province,
                town: town,
                barangay: barangay,
                zipcode: zipcode,
                contact: contact
            },
            dataType:"json",
            success: function(return_data){
                $btn.prop('disabled', false).text('Save Changes');

                if (return_data.validation_status === 'T') {
                    if (return_data.firstname) {
                        sessionStorage.setItem('customers_firstname', return_data.firstname);
                        $('#namesignin').text(return_data.firstname);
                    }
                    var profileInitials = '';
                    if (return_data.firstname) {
                        profileInitials += return_data.firstname.charAt(0).toUpperCase();
                    }
                    if (return_data.lastname) {
                        profileInitials += return_data.lastname.charAt(0).toUpperCase();
                    }
                    if (profileInitials) {
                        $('#topnavProfileInitials').text(profileInitials);
                    }
                    $('.portal-profile-avatar-card h2').text($.trim((return_data.firstname || '') + ' ' + (return_data.lastname || '')));
                    showPanelAlert('#profileMsgbox', return_data.msg, true);
                    return;
                }

                showPanelAlert('#profileMsgbox', return_data.msg || 'Could not update profile.');
            },
            error: function() {
                $btn.prop('disabled', false).text('Save Changes');
                showPanelAlert('#profileMsgbox', 'Something went wrong. Please try again.');
            }
        });

        return false;
    });

    $('#fname, #lname, #contact, #address, #zipcode, #province_text, #town_text, #barangay_text').on('input', function() {
        var id = $(this).attr('id');
        if (id === 'province_text') {
            id = 'province';
        } else if (id === 'town_text') {
            id = 'town';
        } else if (id === 'barangay_text') {
            id = 'barangay';
        }
        clearFieldError(id);
        $('#profileMsgbox').fadeOut('fast');
    });

    $('#country, #province, #town, #barangay').on('change', function() {
        clearFieldError($(this).attr('id'));
        $('#profileMsgbox').fadeOut('fast');
    });

    $('#avatarInput').on('change', function() {
        var file = this.files && this.files[0];
        if (!file) {
            return;
        }

        var allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (allowedTypes.indexOf(file.type) === -1) {
            showPanelAlert('#avatarMsgbox', 'Please choose a JPG, PNG, GIF, or WebP image.');
            $(this).val('');
            return;
        }
        if (file.size > 2 * 1024 * 1024) {
            showPanelAlert('#avatarMsgbox', 'Image must be 2 MB or smaller.');
            $(this).val('');
            return;
        }

        var reader = new FileReader();
        reader.onload = function(e) {
            $('#profileAvatarImg').attr('src', e.target.result).show();
            $('#profileAvatarPlaceholder').hide();
            updateNavProfileAvatar(e.target.result);
        };
        reader.readAsDataURL(file);

        var formData = new FormData();
        formData.append('function', 'uploadAvatar');
        formData.append('avatar', file);

        showPanelAlert('#avatarMsgbox', 'Uploading photo…');

        $.ajax({
            type: 'POST',
            url: 'process.php',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(return_data) {
                if (return_data.validation_status === 'T') {
                    if (return_data.avatar_url) {
                        $('#profileAvatarImg').attr('src', return_data.avatar_url).show();
                        $('#profileAvatarPlaceholder').hide();
                        updateNavProfileAvatar(return_data.avatar_url);
                    }
                    showPanelAlert('#avatarMsgbox', return_data.msg, true);
                    return;
                }
                showPanelAlert('#avatarMsgbox', return_data.msg || 'Could not upload photo.');
            },
            error: function() {
                showPanelAlert('#avatarMsgbox', 'Upload failed. Please try again.');
            }
        });
    });

    $('#frmChangePassword').on('submit', function(event) {
        event.preventDefault();

        if (!runChangePasswordValidation(true)) {
            return false;
        }

        var $btn = $('#btnChangePassword');
        $btn.prop('disabled', true).text('Updating…');

        $.ajax({
            type: 'POST',
            url: 'process.php',
            data: {
                'function': 'ChangePassword',
                current_password: $('#current_password').val(),
                new_password: $('#new_password').val(),
                confirm_password: $('#confirm_password').val()
            },
            dataType: 'json',
            success: function(return_data) {
                $btn.prop('disabled', false).text('Update Password');

                if (return_data.validation_status === 'T') {
                    $('#frmChangePassword')[0].reset();
                    clearChangePasswordErrors();
                    showPanelAlert('#passwordMsgbox', return_data.msg, true);
                    return;
                }

                showPanelAlert('#passwordMsgbox', return_data.msg || 'Could not update password.');
            },
            error: function() {
                $btn.prop('disabled', false).text('Update Password');
                showPanelAlert('#passwordMsgbox', 'Something went wrong. Please try again.');
            }
        });

        return false;
    });

    $('#current_password, #new_password, #confirm_password').on('input', function() {
        clearFieldError($(this).attr('id'));
        $('#passwordMsgbox').fadeOut('fast');
    });

    function runProfileValidation(showAlerts) {
        clearFieldError('fname');
        clearFieldError('lname');
        clearFieldError('contact');
        clearFieldError('address');
        clearFieldError('country');
        clearFieldError('province');
        clearFieldError('town');
        clearFieldError('barangay');
        clearFieldError('zipcode');
        $('#profileMsgbox').hide().empty();

        var hasError = false;
        if ($.trim($('#fname').val()) === '') {
            showFieldError('fname', 'First name is required.');
            hasError = true;
        }
        if ($.trim($('#lname').val()) === '') {
            showFieldError('lname', 'Last name is required.');
            hasError = true;
        }
        if ($('#country').length) {
            var country = typeof getCheckoutCountry === 'function' ? getCheckoutCountry() : ($('#country').val() || '');
            var province = typeof getCheckoutProvince === 'function' ? getCheckoutProvince() : ($('#province').val() || '');
            var town = typeof getCheckoutTown === 'function' ? getCheckoutTown() : ($('#town').val() || '');
            var barangay = typeof getCheckoutBarangay === 'function' ? getCheckoutBarangay() : ($('#barangay').val() || '');
            if (country === '') {
                showFieldError('country', 'Country is required.');
                hasError = true;
            }
            if (province === '') {
                showFieldError('province', 'Province is required.');
                hasError = true;
            }
            if (town === '') {
                showFieldError('town', 'Town / City is required.');
                hasError = true;
            }
            if (barangay === '') {
                showFieldError('barangay', 'Barangay is required.');
                hasError = true;
            }
        }
        if ($('#contact').length && $.trim($('#contact').val()) !== '' && typeof validatePhone === 'function' && !validatePhone($('#contact').val())) {
            showFieldError('contact', 'Enter a valid mobile number (at least 10 digits).');
            hasError = true;
        }

        if (hasError && showAlerts) {
            showPanelAlert('#profileMsgbox', 'Please fix the highlighted fields below.');
        }

        return !hasError;
    }

    function clearChangePasswordErrors() {
        clearFieldError('current_password');
        clearFieldError('new_password');
        clearFieldError('confirm_password');
        $('#passwordMsgbox').hide().empty();
    }

    function runChangePasswordValidation(showAlerts) {
        clearChangePasswordErrors();

        var current = $('#current_password').val();
        var newPass = $('#new_password').val();
        var confirmPass = $('#confirm_password').val();
        var hasError = false;

        if (current === '') {
            showFieldError('current_password', 'Current password is required.');
            hasError = true;
        }
        if (newPass === '') {
            showFieldError('new_password', 'New password is required.');
            hasError = true;
        } else if (newPass.length < 8) {
            showFieldError('new_password', 'Password must be at least 8 characters.');
            hasError = true;
        }
        if (confirmPass === '') {
            showFieldError('confirm_password', 'Please confirm your new password.');
            hasError = true;
        } else if (newPass !== confirmPass) {
            showFieldError('confirm_password', 'Passwords do not match.');
            hasError = true;
        }
        if (!hasError && current !== '' && current === newPass) {
            showFieldError('new_password', 'Must be different from current password.');
            hasError = true;
        }

        if (hasError && showAlerts) {
            showPanelAlert('#passwordMsgbox', 'Please fix the highlighted fields below.');
        }

        return !hasError;
    }

    $("select#company").change(function(){

        var selectedCompany = $("#company option:selected").val();

        $.ajax({
            type: "POST",
            url: "process.php",
            data: {
                'function':'getDepartment',
                company : selectedCompany
            },
            dataType:"json",
            success: function(return_data){
             $("#department").html(return_data.msg);

            }

        });

    });

    $('#btnLogin').click(function(){
        if (!runLoginValidation(true)) {
            return;
        }

        var email = $('#emaillogin').val().trim();
        var password = $('#passwordlogin').val();
        var $btn = $('#btnLogin');

        $btn.prop('disabled', true).text('Signing in…');

        $.ajax({
            type:"POST",
            url:"process.php",
            data: {
                'function':'LoginValidate',
                'email': email,
                'password': password
            },
            dataType: "json",
            success: function(return_data){
                $btn.prop('disabled', false).text('Sign In');

                if(return_data.validation_status=='T'){
                  sessionStorage.customers_id=return_data.customers_id;
                  sessionStorage.setItem("customers_firstname", return_data.customers_firstname);
                  // Cart/checkout flow sets fhLoginRedirect=checkout.php; regular login → home
                  var redirectTo = (typeof window.fhLoginRedirect === 'string' && window.fhLoginRedirect)
                      ? window.fhLoginRedirect
                      : 'home.php';
                  window.location.href = redirectTo;
                  return;
                }

                showLoginAlert(return_data.msg || 'Unable to sign in. Please try again.');
            },
            error: function() {
                $btn.prop('disabled', false).text('Sign In');
                showLoginAlert('Something went wrong. Please check your connection and try again.');
            }
        });
    });

    $('#emaillogin, #passwordlogin').on('input', function() {
        clearFieldError($(this).attr('id'));
        $('#loginMsgbox').fadeOut('fast');
    });

    $('#emaillogin, #passwordlogin').on('keypress', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            $('#btnLogin').click();
        }
    });

    function clearFieldError(fieldId) {
        $('#' + fieldId).removeClass('portal-input-error');
        $('#' + fieldId + '-error').text('');
    }

    function showFieldError(fieldId, message) {
        $('#' + fieldId).addClass('portal-input-error');
        $('#' + fieldId + '-error').text(message);
    }

    function showPanelAlert(selector, message, isSuccess) {
        var $box = $(selector);
        $box.removeClass('is-success');
        if (isSuccess) {
            $box.addClass('is-success');
        }
        $box.html(message).show();
        if ($box[0]) {
            $box[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }

    function clearLoginErrors() {
        clearFieldError('emaillogin');
        clearFieldError('passwordlogin');
        $('#loginMsgbox').hide().empty();
    }

    function showLoginAlert(message) {
        showPanelAlert('#loginMsgbox', message);
    }

    function runLoginValidation(showAlerts) {
        clearLoginErrors();

        var email = $('#emaillogin').val().trim();
        var password = $('#passwordlogin').val();
        var hasError = false;

        if (email === '') {
            showFieldError('emaillogin', 'Email is required.');
            hasError = true;
        } else if (!validateEmail(email)) {
            showFieldError('emaillogin', 'Please enter a valid email address.');
            hasError = true;
        }

        if (password === '') {
            showFieldError('passwordlogin', 'Password is required.');
            hasError = true;
        } else if (password.length < 4) {
            showFieldError('passwordlogin', 'Password must be at least 4 characters.');
            hasError = true;
        }

        if (hasError && showAlerts) {
            showLoginAlert('Please fix the highlighted fields below.');
        }

        return !hasError;
    }

    function clearRegisterErrors() {
        clearFieldError('email');
        clearFieldError('psw');
        clearFieldError('psw-repeat');
        $('#registerMsgbox').hide().empty();
    }

    function runRegisterValidation(showAlerts) {
        clearRegisterErrors();

        var email = $('#email').val().trim();
        var psw = $('#psw').val();
        var pswRepeat = $('#psw-repeat').val();
        var hasError = false;

        if (email === '') {
            showFieldError('email', 'Email is required.');
            hasError = true;
        } else if (!validateEmail(email)) {
            showFieldError('email', 'Please enter a valid email address.');
            hasError = true;
        }

        if (psw === '') {
            showFieldError('psw', 'Password is required.');
            hasError = true;
        } else if (psw.length < 4) {
            showFieldError('psw', 'Password must be at least 4 characters.');
            hasError = true;
        }

        if (pswRepeat === '') {
            showFieldError('psw-repeat', 'Please confirm your password.');
            hasError = true;
        } else if (psw !== pswRepeat) {
            showFieldError('psw-repeat', 'Passwords do not match.');
            hasError = true;
        }

        if (hasError && showAlerts) {
            showPanelAlert('#registerMsgbox', 'Please fix the highlighted fields below.');
        }

        return !hasError;
    }

    ////////////
    $(document).on('click', '.btnaddtocart', function(){
        var id=$(this).attr('id');
        var qty=$('#'+id+'_qty').val();
        var price=$('#'+id+'_price').val();
        var name=$('#'+id+'_name').val();
        var menudate = null;
        if ($(this).hasClass('btn-preorder')) {
            menudate = $('#preorder_menudate').val() || null;
        }
        my_function(id,qty,price,name,menudate);
    })
    ////
    my_function=function my_function(id,qty,price,name,menudate){
        var payload = {
            'function': 'AddtoCart',
            'p_id':id,
            'qty':qty,
            'price': price,
            'name':name
        };
        if (menudate) {
            payload.menudate = menudate;
        }
        $.ajax({
               type: "POST",
               url: "process.php",
               data: payload,
               dataType: "json",

               success: function(return_data){
                    updateCartCountDisplay(return_data.size_of_cart);

                    if (return_data.validation_status === 'F') {
                        swal({
                            title: 'Notice',
                            text: return_data.msg,
                            type: 'error',
                            timer: 2500,
                            showConfirmButton: false
                        });
                    } else {
                        swal({
                            title: 'Added to Cart!',
                            text: return_data.msg,
                            type: 'success',
                            timer: 2000,
                            showConfirmButton: false
                        });
                    }
               },
         });


        /*$.post('process.php',{
            'function':'AddtoCart',
            'p_id':id,
            'qty':qty
        },function(return_data){
            alert(return_data.msg);
            $("#msg_display").html(return_data.msg);
            $("#cartcounter").html(return_data.size_of_cart);
            $("#msg_display").show();
            setTimeout(function() { $("#msg_display").fadeOut('slow'); }, 4000);
        },"json");*/
    }

    ////////////
    $('.cart-remove').click(function(){  // remove button is clicked.
        my_function($(this).data('remove_id'),0);//Post data
        $('#'+$(this).data('remove_id')+'_qty').spinner("value",0);
    })
    ////
    /*var debouncedStop = _.debounce(function(e, ui) {
          show_error();
    }, 10000);*/
    ////////
    /*$(".selector").spinner({
        max: 10,
        min: 0,
        stop:  _.debounce(function(e, ui) {
    	    var id=$(this).data('sp_id');
    	    var qty=$('#'+id+'_qty').val();

    	    my_function(id,qty);

    	}, 500)
     });*/
    //$(".selector").spinner( "value", 2 );
    ////
    $('.btnForgotPassword').on('click', function (e) {
        e.preventDefault();
        window.location.href = 'forgot_password.php';
    });

    $('#frmForgotPassword').on('submit', function(event) {
        event.preventDefault();

        if (!runForgotValidation(true)) {
            return false;
        }

        var email = $('#forgotEmail').val().trim();
        var $btn = $('#btnForgotSubmit');

        $btn.prop('disabled', true).text('Sending…');

        $.ajax({
            type: 'POST',
            url: 'process.php',
            data: {
                'function': 'ForgotPassword',
                'email': email
            },
            dataType: 'json',
            success: function(return_data) {
                $btn.prop('disabled', false).text('Send Reset Email');

                if (return_data.validation_status === 'T') {
                    $('#forgotSuccessEmail').text(email);
                    $('#forgotFormPanel').hide();
                    $('#forgotSuccessPanel').show();
                    $('#forgotSuccessPanel')[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    return;
                }

                showPanelAlert('#forgotMsgbox', return_data.msg || 'Unable to send reset email. Please try again.');
            },
            error: function() {
                $btn.prop('disabled', false).text('Send Reset Email');
                showPanelAlert('#forgotMsgbox', 'Something went wrong. Please check your connection and try again.');
            }
        });

        return false;
    });

    $('#forgotEmail').on('input', function() {
        clearFieldError('forgotEmail');
        $('#forgotMsgbox').fadeOut('fast');
    });

    $('#forgotEmail').on('keypress', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            $('#frmForgotPassword').submit();
        }
    });

    function clearForgotErrors() {
        clearFieldError('forgotEmail');
        $('#forgotMsgbox').hide().empty();
    }

    function runForgotValidation(showAlerts) {
        clearForgotErrors();

        var email = $('#forgotEmail').val().trim();
        var hasError = false;

        if (email === '') {
            showFieldError('forgotEmail', 'Email is required.');
            hasError = true;
        } else if (!validateEmail(email)) {
            showFieldError('forgotEmail', 'Please enter a valid email address.');
            hasError = true;
        }

        if (hasError && showAlerts) {
            showPanelAlert('#forgotMsgbox', 'Please enter a valid email address to continue.');
        }

        return !hasError;
    }

});