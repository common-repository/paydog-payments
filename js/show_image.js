
jQuery(function($){
     var payUrl = function() {
                var bank = $('#woocommerce_paydog_bank').val()
                var requestId = $('#paydog_request_id').val()

                //alert( "new bank:" + bank + " requestId:" + requestId );

                return  "https://www.paydog.co.uk/r/" + requestId  + "/authorise?immediate=true&bank=" + bank

           }

    var bankChange = function() {
               var url = payUrl()
               var imageUrl = "https://api.paydog.co.uk/paydog/api/qrcode?url=" +encodeURIComponent(url)
               $("#paydog_qr_code").attr("src", imageUrl)
               $("#paydog_link").attr("href", url)
           }


    var showCountDownTimer = function() {
        var timeLeft = 180;
        var downloadTimer = setInterval(function(){
          if(timeLeft <= 0){
            clearInterval(downloadTimer);
            hideQRCode();
          }
          $("#paydog_progress_bar").html(timeLeft + " seconds remaining to authorise payment");
          timeLeft -= 1;
        }, 1000);
    }

    var showQRCode = function() {
        bankChange();
        $('#paydog_payment').show()
    }

    var hideQRCode = function() {
        bankChange();
        $('#paydog_payment').hide()
    }


    var checkReadyToPay = function(data) {
            var requestId = $('#paydog_request_id').val()
//        $.ajax({
//            type: "GET",
//            url: "https://api.paydog.co.uk/api/request/" + requestId,
//            // The key needs to match your method's input parameter (case-sensitive).
//            success: successCallback,
//            error: errorCallback
//            },false);
    }

    const popupCenter = function(url, title, w, h){
        // Fixes dual-screen position                             Most browsers      Firefox
        const dualScreenLeft = window.screenLeft !==  undefined ? window.screenLeft : window.screenX;
        const dualScreenTop = window.screenTop !==  undefined   ? window.screenTop  : window.screenY;

        const width = window.innerWidth ? window.innerWidth : document.documentElement.clientWidth ? document.documentElement.clientWidth : screen.width;
        const height = window.innerHeight ? window.innerHeight : document.documentElement.clientHeight ? document.documentElement.clientHeight : screen.height;

        const systemZoom = width / window.screen.availWidth;
        const left = (width - w) / 2 / systemZoom + dualScreenLeft
        const top = (height - h) / 2 / systemZoom + dualScreenTop
        const newWindow = window.open(url, title,
          `
          scrollbars=yes,
          width=${w / systemZoom},
          height=${h / systemZoom},
          top=${top},
          left=${left},
          toolbar=no,
          status=no,
          menubar=no
          `
        )

        if (window.focus) newWindow.focus();
    }

    var readyToPay = function(data) {
//            popupCenter(payUrl(), null,375,700);
            if(window.innerWidth < 768){
                window.location = payUrl();
            } else {
                showQRCode()
                showCountDownTimer()
            }
    }

    var createRequest = function(e) {
        e.preventDefault(e);

        var checkout_form = $( 'form[name="checkout"]'  );

        // deactivate the tokenRequest function event
        checkout_form.off( 'submit', createRequest );

        setTimeout(readyToPay, 2000)

        // submit the form now
        checkout_form.submit();


        return false;
    };


    $('form[name="checkout"]').submit(createRequest);
});