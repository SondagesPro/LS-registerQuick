/**
 * @file Description
 * @author Denis Chenu
 * @copyright Denis Chenu <http://www.sondages.pro>
 * @license magnet:?xt=urn:btih:1f739d935676111cfff4b4693e3816e664797050&dn=gpl-3.0.txt GPL-v3-or-Later
 * @license magnet:?xt=urn:btih:0b31508aeb0634b347b8270c7bee4d411b5d4109&dn=agpl-3.0.txt AGPL v3.0
 * @license magnet:?xt=urn:btih:d3d9a9a6595521f9666a5e94cc830dab83b65699&dn=expat.txt Expat (MIT)
 */

$(document).off('change','select.languagechanger');
$(document).on('change','select.languagechanger',function(){
    $("form#limesurvey [name='lang']").remove();// Remove existing lang selector
    $("<input type='hidden']>").attr('name','lang').val($(this).find('option:selected').val()).appendTo($('form#limesurvey'));
    $('form#limesurvey').find('[required]').removeAttr('required');
    $("#changelangbtn").appendTo($('form#limesurvey'));
    $('#changelangbtn').click();
});
