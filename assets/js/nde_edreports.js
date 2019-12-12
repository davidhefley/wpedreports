//jQuery('#edReportHolder').css('opacity',0);
var filterTimeout = '';
var $popup = '';

/**
 * Deterine the type of animation event to return
 * @type animations|nde_edreportsanimationEnd.animations
 */
var animationEnd = (function (el) {
    var animations = {
        animation: 'animationend',
        OAnimation: 'oAnimationEnd',
        MozAnimation: 'mozAnimationEnd',
        WebkitAnimation: 'webkitAnimationEnd',
    };

    for (var t in animations) {
        if (el.style[t] !== undefined) {
            return animations[t];
        }
    }
})(document.createElement('div'));

/**
 * jQuery ready event
 */
jQuery(document).ready(function ($) {
    $popup = jQuery('#edreportdetails'); //reference for the popup report to prevent requeries
    $('.ndetooltip').tooltip(); //activate tooltips

    $('#edreportFilterForm').on('submit',function(){
        jQuery('#filterMessage').slideUp('fast');
        if (filterTimeout) clearTimeout(filterTimeout);
        refreshseries();
        return false;
    });

    //grade selector click function

    $(document).on('click', '.edreport_filter .filterbystatus > div', function () {
            /*let isActive = $(this).hasClass('active');
            if ( $('.edreport_filter .filterbystatus > div.active').length == 1 && isActive ) {
                alert('At least one status must be selected.');
                return false;
            }*/
            $(this).toggleClass('active');
            if (filterTimeout)
                clearTimeout(filterTimeout);
            filterTimeout = setTimeout(refreshseries, 750);

    })

    $(document).on('click', '.edreport_filter .fakeCheckBox', function () {

        if (jQuery('.edreport_filter .fakeCheckBox.active').length === 1 && jQuery(this).is('.active')) {
            jQuery('#filterMessage').slideDown();
        }

        $(this).toggleClass('active');
        $checkbox = $(this).next();
        if ($(this).hasClass('active')) {
            $checkbox.prop('checked', false);
        } else {
            $checkbox.prop('checked', true);
        }
        if (filterTimeout)
            clearTimeout(filterTimeout);
        filterTimeout = setTimeout(refreshseries, 750);
    });

    //search on title text typing
    $(document).on('keyup', '.edreport_filter #q', function (event) {
        if ( event.keyCode == 13 ) return true;

        if (filterTimeout)
            clearTimeout(filterTimeout);
        filterTimeout = setTimeout(refreshseries, 750);
    });

    //slick slider filmstrip
    applySlick(jQuery);

    //get the next page of reports
    $('.loadmorereports').on('click', function () {
        var $b = $(this);
        $b.prop('disabled', true).css('opacity', '0.5');
        $b.css('width', 'auto');
        let w = $b.outerWidth( true );
        $b.css('width', w);
        $b.html('<div class="fa-1x"><span class="fas fa-spin fa-cog"></span></div>');
        var page = edrep.page * 1;
        var perpage = edrep.perpage * 1;
        var subject = edrep.subject * 1;

        var pCount = jQuery('.edrep_review').length;
        loadSeries(page, perpage, subject, function () {
            edrep.page = (edrep.page * 1) + 1;
            $b.html('Load More Reviews...');
            $b.prop('disabled', false).css('opacity', '1');
            let aCount = jQuery('.edrep_review').length;
            //we didn't get back an even amount, sooooo we should remove the more button
            if (aCount - pCount != perpage)
                $b.hide();
            $b.css('width', 'auto');
        });
    });

    //when they click on the close "X" in the pop-up reports
    jQuery('.popup-container .close').on('click', hideDetails);

    //when they click on the more details for the the poepup report
    jQuery(document).on('click', '.report-link a', function () {
        showDetails(this);
    });

    //toggle grade selections in filter
    jQuery('.gradeToggle').on('click', function () {
        jQuery('.filterbody .fakeCheckBox').toggleClass('active');
        if (jQuery('.edreport_filter .fakeCheckBox.active').length === 0) {
            jQuery('.filterbody .fakeCheckBox').toggleClass('active');
            jQuery('#filterMessage').slideDown('fast');
            return false;
        }
        jQuery('#filterMessage').slideUp('fast');
        if (filterTimeout)
            clearTimeout(filterTimeout);
        filterTimeout = setTimeout(refreshseries, 750);
    });

});

//clear reports, and load fresh when a new filter is configured
function refreshseries() {
    jQuery('#edReportHolder').html('');
    let $b = jQuery('.loadmorereports');
    $b.show();
    $b.html('Load More Reviews...');
    let w = $b.outerWidth();
    $b.css('width', w);
    var perpage = edrep.perpage * 1;
    $b.prop('disabled', true).css('opacity', '0.5').html('<div class="fa-1x"><span class="fas fa-spin fa-cog"></span></div>');
    loadSeries(0, edrep.perpage, edrep.subject, function () {
        edrep.page = (edrep.page * 1) + 1;
        $b.html('Load More Reviews...');
        $b.prop('disabled', false).css('opacity', '1');
        let aCount = jQuery('.edrep_review').length;
        //we didn't get back an even amount, sooooo we should remove the more button
        if (aCount != perpage)
            $b.hide();
    });
}

//load the series results
function loadSeries(page, perpage, subject, callback) {
    let grades = [];
    let status = [];
    jQuery('.filterbody .fakeCheckBox.active').each(function (i, e) {
        grades.push(jQuery(this).data('val'));
    });

    jQuery('.filterbody .filterbystatus > div.active').each(function (i, e) {
        status.push(jQuery(this).data('status'));
    });

    jQuery.ajax({
        url: edrep.ajaxurl,
        data: {
            grades: grades,
            status: status,
            textsearch: jQuery('#q').val(),
            action: 'edreportnextpage',
            page: page,
            perpage: perpage,
            subject: subject,
            type: edrep.type,

        },
        dataType: 'html',
    })
    .done(function (d) {
        jQuery('#edReportHolder').append(d);
        applySlick(jQuery);
        jQuery('#filterMessage:visible').slideUp('fast');

    })
    .fail(function () {
        alert('There was an error retrieving your information. Please try again later.');
    })
    .always(function () {
        if (typeof callback == 'function')
            callback();
    });
}

//configure and apply the slick slider affect
function applySlick($) {
    $('.reports').last().on('init', function (event, slick) {
        console.log('Slick init');
        $('#edReportHolder').animate({'opacity': 1}, 2000);
    });
    $('.reports').not('.slick-slider').slick({
                "infinite": false,
                responsive: [{
                    breakpoint: 1024,
                    settings: {
                        slidesToShow: 2,
                        slidesToScroll:2,
                        infinite: false
                    }
                    }, {
                        breakpoint: 768,
                        settings: {
                            slidesToShow: 1,
                            slidesToScroll:1,
                            dots: false
                        }

                    }, {

                        breakpoint: 300,
                        settings: "unslick" // destroys slick

                    }]
            }
    );
}

//translate the ratings into proper english
function getRatingText(x) {
    switch (x) {
        case 'meets':
            return 'Meets Expectations';
        case 'partially-meets':
            return 'Partially Meets Expectations';
        case 'does-not-meet':
            return 'Does Not Meet Expectations';
        case 'did-not-review':
            return 'Did Not Review';
        default:
            return x;
    }
}


//popup the ratings report
function showDetails(a) {
    var data = jQuery(a).data('report');
    console.log(data);
    jQuery('.title', $popup).text(data.title);
    jQuery('.grade', $popup).text(data.grade);
    jQuery('.view a', $popup).attr('href', data.url);
    var alignment_status = '';
    if (data.gw_1.rating == 'meets' && ( data.gw_2 && data.gw_2 && data.gw_2.rating == 'meets' ) ) {
        alignment_status = 'meets';
    }

    if (data.gw_1.rating == 'partially-meets' || data.gw_2.rating == 'partially-meets') {
        alignment_status = 'partially-meets';
    }

    if (data.gw_1.rating == 'does-not-meet' || data.gw_2.rating == 'does-not-meet') {
        alignment_status = 'does-not-meet';
    }

    jQuery('fieldset:eq(0)', $popup).attr('class', '').addClass(alignment_status);
    jQuery('fieldset:eq(0) .status>div>span', $popup).text(getRatingText(alignment_status));
    jQuery('fieldset:eq(1)', $popup).attr('class', '').addClass(data.gw_3.rating);
    jQuery('fieldset:eq(1) .status>div>span', $popup).text(getRatingText(data.gw_3.rating));

    jQuery('.result', $popup).remove();
    jQuery('.scales a.gw-1', $popup).attr('href', data.url + '#gateway_1');
    jQuery('.scales a.gw-2', $popup).attr('href', data.url + '#gateway_2');
    jQuery('.scales a.gw-3', $popup).attr('href', data.url + '#gateway_3');

    buildRatingScales(data, $popup);
    jQuery('#edreportdetails').addClass('animated slideInLeft').show();
    jQuery('.ndemodaler').slideDown();
    jQuery('.ndemodaler').one('click', hideDetails);

    jQuery('.scales > a > .ttl', $popup).css('height', 'auto');
    var max = 0;
    jQuery('.scales > a > .ttl', $popup).each(function () {
        let h = jQuery(this).outerHeight(true);
        if (h > max)
            max = h;
    }).css('height', max);

    jQuery(document).on('keyup', function(e){
        if ( e.keyCode == 27 ) hideDetails();
    });

}




//setup the charts for the ratings report
function buildRatingScales(data, target) {
    var repType = data.type;
    var typeData = edrep.intervals[ repType ];
    for (var i = 1; i <= 4; i++) {
        if ( typeof typeData[i] == 'undefined' ) continue;
        var liString = '';
        var indiceString = '';
        for (let x = 1; x <= (parseInt(typeData[i].intervals['meets']['max'])); x++) {
            var myclass = 'did-not-review-segment';
            liString += '<li class="did-not-review-segment">' + x + '<img src="' + edrep.images + 'did-not-review.png"></li>';
            if ((x - 1) < parseInt(typeData[i].intervals['does-not-meet']['max']))
                myclass = 'does-not-meet';
            else if ((x - 1) < parseInt(typeData[i].intervals['partially-meets']['max']))
                myclass = 'partially-meets';
            else
                myclass = 'meets';

            indiceString += '<li class="' + myclass + '">' + x + '<img src="' + edrep.images + myclass + '.png">&nbsp;<span></span></li>';
        }

        jQuery('a.gw-' + i + ' .expectations ul', target).html(liString);
        jQuery('a.gw-' + i + ' .indices ul', target).html(indiceString);
        jQuery('a.gw-' + i + ' .indices ul li:eq(0)', target).addClass('indice').find('span').text(0);
        jQuery('a.gw-' + i + ' .indices ul li:last-child', target).addClass('indice').find('span').text(typeData[i].intervals['meets']['max']);
        jQuery('a.gw-' + i + ' .indices ul li:eq(' + typeData[i].intervals['does-not-meet']['max'] + ')', target).addClass('indice').find('span').text(typeData[i].intervals['partially-meets']['min']);
        jQuery('a.gw-' + i + ' .indices ul li:eq(' + typeData[i].intervals['partially-meets']['max'] + ')', target).addClass('indice').find('span').text(typeData[i].intervals['meets']['min']);
        if (i !== 3)
            jQuery('a.gw-' + i + ' p.ttl', target).text(typeData[i].title);



        setScaleValues(i, data['gw_' + i].rating, data['gw_' + i].score, target);
    }
}

//apply appropriate classess to the ratings report
function setScaleValues(gw, rating, score, target) {
    var $scale = jQuery('.scales a.gw-' + gw + ' .expectations ul li', target);
    if (rating !== 'did-not-review') {
        let i = score - 1;
        if ( score == 0 ) i = 0;

        $scale.eq( i ).append('<span class="result gw_' + gw + ' animated fadeInUp" style="opacity:0">' + score + '</span>');
        if ($scale.length == score) {
            $scale.removeClass('did-not-review-segment').addClass(rating + '-segment').find("img").hide();
            $scale.find('img').attr('src', edrep.images + rating + '.png');
        } else {
            $scale.slice(0, i).removeClass('did-not-review-segment').addClass(rating + '-segment').find("img").hide();
            $scale.slice(0, i).find('img').attr('src', edrep.images + rating + '.png');
        }
    } else {
        $scale.find('.result').remove();
        $scale.attr('class', 'did-not-review-segment');
        $scale.find('img').attr('src', edrep.images + 'did-not-review.png');
    }
}

//close the popup report
function hideDetails() {
    jQuery(document).off('keyup');
    jQuery('#edreportdetails').one(animationEnd, function () {
        jQuery(this).hide().removeClass('animated slideOutRight');
    });
    jQuery('.ndemodaler').slideUp();
    jQuery('#edreportdetails').addClass('slideOutRight');
    setTimeout(function () {
        jQuery('#edreportdetails').removeClass('animated slideOutRight');
    }, 500);

}

