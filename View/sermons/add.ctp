<?php
/**
 * Sermon upload form.
 * This form is responsible for uplaoding sermons onto an Urg system.
 *
 * @author Amos Chan <amos.chan@chapps.org>
 * @since v 1.0
 */
 ?>
<?php echo $this->Html->scriptStart(); ?>
    var image_in_progress = false;
    var attachment_in_progress = false;
    var submit_form = false;
    function on_complete_images(event, ID, fileObj, response, data) {
        if ($("#SermonBannerAttachmentIndex").val() == "") {
            $("#SermonBannerAttachmentIndex").val($("input.attachment").size());
        }

        bannerIndex = $("#SermonBannerAttachmentIndex").val();

        if ($("#Attachment" + bannerIndex + "Filename").length == 0) {
            $('<input>').attr({ 
                    type: 'hidden', 
                    id: 'Attachment' + bannerIndex + 'Filename', 
                    name: 'data[Attachment][' + bannerIndex + '][filename]' ,
                    value: fileObj.name,
                    class: "attachment"
            }).appendTo('form');
            $('<input>').attr({ 
                    type: 'hidden', 
                    id: 'Attachment' + bannerIndex + 'AttachmentTypeId', 
                    name: 'data[Attachment][' + bannerIndex + '][attachment_type_id]' ,
                    value: <?php echo $banner_type["AttachmentType"]["id"]; ?>,
            }).appendTo('form');
        }

        banner_width = $("#sermon-banner").width();

        $("#sermon-banner").html(
                "<img id='#sermon-banner-img' src='" +
                "<?php echo $this->Html->url("/urg_post/img/" . $this->data["Post"]["id"]); ?>" 
                + "/" + fileObj.name + "#" + Math.random() + "' style='width: " + banner_width +  "px;' />");
    }

    function on_complete_attachments(event, ID, fileObj, response, data) {
        attachmentCounter = $("input.attachment").size();
        response = jQuery.parseJSON(response);
        $('<input>').attr({ type: 'hidden', 
                id: 'Attachment' + attachmentCounter + 'Filename', 
                name: 'data[Attachment][' + attachmentCounter + '][filename]' ,
                value: fileObj.name,
                class: "attachment"
        }).appendTo('form');
        $('<input>').attr({ 
                type: 'hidden', 
                id: 'Attachment' + attachmentCounter + 'AttachmentTypeId', 
                name: 'data[Attachment][' + attachmentCounter + '][attachment_type_id]' ,
                value: response.attachment_type_id,
        }).appendTo('form');

        $("<li>").attr({ id: "AttachmentQueueListItem" + attachmentCounter })
                .appendTo("#attachment-queue");

        $("<a>").attr({
                href: "<?php echo $this->Html->url("/urg_post/") ?>" + response.webroot_folder + 
                        "/<?php echo $this->data["Post"]["id"] ?>/" + fileObj.name,
                id: "AttachmentQueueAudioLink" + attachmentCounter ,
                target: "_blank"
        }).appendTo("#AttachmentQueueListItem" + attachmentCounter);

        $("#AttachmentQueueAudioLink" + attachmentCounter).text(fileObj.name.substring(0, 40));
    }

    function image_upload_in_progress(event, ID, fileObj, data) {
        image_in_progress = true;
    }

    function attachment_upload_in_progress(event, ID, fileObj, data) {
        attachment_in_progress = true;
    }
<?php echo $this->Html->scriptEnd(); ?>
<?php echo $this->Html->script("/urg_post/js/jquery.timepicker.min.js"); ?>

<div class="sermons form">
<?php echo $this->Form->create('Sermon'); ?>
    <div class="grid_6 right-border">
        <fieldset>
            <legend> <div> <h2><?php echo __('Add Sermon'); ?></h2> </div> </legend>
            <?php
            echo $this->Form->hidden("id");
            echo $this->Form->hidden("bannerAttachmentIndex");
            echo $this->Form->input("series_name", array("label"=>__("Series")));
            echo $this->Html->div("error-message", "", 
                    array("id"=>"SermonSeriesNameError", "style"=>"display: none"));
            echo $this->Html->div("validated", "✓", 
                    array("id"=>"SermonSeriesNameValid", "style"=>"display: none"));
            echo $this->Form->input('Post.title');
            echo $this->Html->div("error-message", "", 
                    array("id"=>"PostTitleError", "style"=>"display: none"));
            echo $this->Html->div("validated", "✓", 
                    array("id"=>"PostTitleValid", "style"=>"display: none"));
            echo $this->Form->input("speaker_name", 
                    array("label"=>__("Speaker")));
            echo $this->Html->div("error-message", "", 
                    array("id"=>"SermonSpeakerNameError", "style"=>"display: none"));
            echo $this->Html->div("validated", "✓", 
                    array("id"=>"SermonSpeakerNameValid", "style"=>"display: none"));
            echo $this->Form->input("passages");
            echo $this->Form->hidden("Post.formatted_date");
            echo $this->Form->input("Post.displayDate", 
                    array("type"=>"text", "label"=>__("Date"), "after"=>$this->Form->text("Post.displayTime", array("div"=>false, "label"=>false))));
            echo $this->Markdown->input('Sermon.description', array("label"=>__("Description"), "rows"=>"8"));
            echo $this->Markdown->input('Post.content', array("label"=>__("Notes"), "rows"=>"20"));
            ?>
        </fieldset>
    </div>
    <div class="grid_3 suffix_3">
        <fieldset>
            <legend> <div> <h2><?php echo __('Add Resources'); ?></h2> </div> </legend>
            <?php 
            echo $this->Html->div("input", 
                    $this->Html->div("placeholder", "", array("id" => "sermon-banner")) . 
                    $this->element("Cuploadify.uploadify", 
                    array("plugin" => "Cuploadify", 
                            "dom_id" => "image_upload", 
                            "session_id" => CakeSession::id(),
                            "include_scripts" => array("uploadify_css", "uploadify", "swfobject"),
                            "options" => array("auto" => true, 
                                    "folder" => "/" . $this->data["Sermon"]["id"],
                                    "script" => $this->Html->url("/urg_sermon/sermons/upload_image"),
                                    "buttonText" => strtoupper(__("Add Banner")), 
                                    //"multi" => true,
                                    //"queueID" => "upload_queue",
                                    "removeCompleted" => true,
                                    "fileExt" => "*.jpg;*.jpeg;*.png;*.gif;*.bmp",
                                    "fileDataName" => "imageFile",
                                    "fileDesc" => "Image Files",
                                    "onComplete" => "on_complete_images",
                                    "onProgress" => "image_upload_in_progress",
                                    "onAllComplete" => "image_uploads_completed"
                                    )))); 
            echo $this->Html->div("input", $this->element("Cuploadify.uploadify",
                    array("plugin" => "Cuploadify", 
                            "dom_id" => "attachment_upload", 
                            "session_id" => CakeSession::id(),
                            "options" => array("auto" => true, 
                                    "folder" => "/" . $this->data["Sermon"]["id"],
                                    "script" => $this->Html->url("/urg_sermon/sermons/upload_attachments"),
                                    "buttonText" => strtoupper(__("Attachments")), 
                                    "removeCompleted" => true,
                                    "fileExt" => "*.mp3;*.jpg;*.jpeg;*.png;*.gif;*.bmp;" .
                                                 "*.ppt;*.pptx;*.doc;*.docx;*.pdf",
                                    "fileDataName" => "attachmentFile",
                                    "fileDesc" => "Sermon Attachments",
                                    "multi" => true,
                                    "onComplete" => "on_complete_attachments",
                                    "onProgress" => "attachment_upload_in_progress",
                                    "onAllComplete" => "attachment_uploads_completed"
                                    ))));
            echo $this->Html->div("", $this->Html->tag("ul", "", array("id"=>"attachment-queue")));
            ?>
        </fieldset>
    </div>
    <div class="grid_6 suffix_6">
        <?php echo $this->Form->end(__('Upload Sermon'));?>
    </div>
    <?php 
        echo $this->Html->div("", $this->Html->image("/urg_sermon/img/loading.gif"), 
                array("id" => "loading-validate", "style" => "display: none")); 
    ?>
    <div style="display: none;" id="in-progress" title="<?php echo __("Uploads pending..."); ?>">
        <p>
            <?php echo __("The sermon form will be submitted after all attachments have been uploaded."); ?>
        </p>
    </div>
</div>
<?php echo $this->Html->scriptStart(); ?>
    function on_validate(dom_id, XMLHttpRequest, textStatus) {
        $("#loading-validate").hide();
        
        if ($(dom_id + "Error").text() == "") {
            $(dom_id + "Error").hide();
            $(dom_id).after($(dom_id + "Valid"));
            $(dom_id + "Valid").show();
            $(dom_id).removeClass("invalid");
        } else {
            $(dom_id + "Valid").hide();
            $(dom_id).after($(dom_id + "Error"));
            $(dom_id + "Error").show();
            $(dom_id).addClass("invalid");
        }
    }

    function loading_validate(dom_id) {
        $(dom_id).after($("#loading-validate"));
        $(dom_id + "Error").hide();
        $("#loading-validate").show();
    }

    $("#PostTitle").blur(function() {
        if ($(this).hasClass("dirty")) {
        <?php
        $this->Js->get("#PostTitle");
        echo $this->Js->request("/urg_sermon/sermons/validate_field/Post/title", array(
                "update" => "#PostTitleError",
                "async" => true,
                "data" => '{ value: $("#PostTitle").val() }',
                "dataExpression" => true,
                "complete" => "on_validate('#PostTitle', XMLHttpRequest, textStatus)",
                "before" => "loading_validate('#PostTitle')"
        ));
        ?>
        }

        $(this).removeClass("dirty");
    });

    var search_series = true;
    var search_speaker = true;

    $(function() {
        $("#SermonSeriesName").autocomplete({
            source: "<?php echo $this->Html->url(
                    array("plugin" => "urg_sermon", 
                          "controller" => "series", 
                          "action" => "autocomplete")
            ); ?>",
            minLength: 0,
            select: function(event, ui) {
                $("#SermonSeriesName").val(ui.item.id);
            },
            search: function(event, ui) {
                search_series = false;
                if ($(this).val().length == 1) {
                    $(this).autocomplete("close");
                    return false;
                }
            },
            close: function(event, ui) {
                search_series = true;
                <?php
                $this->Js->get("#SermonSeriesName");
                echo $this->Js->request("/urg_sermon/sermons/validate_field/Sermon/series_name", 
                    array(
                        "update" => "#SermonSeriesNameError",
                        "async" => true,
                        "data" => '{ value: $("#SermonSeriesName").val() }',
                        "dataExpression" => true,
                        "complete" => "on_validate('#SermonSeriesName', XMLHttpRequest, textStatus)",
                        "before" => "loading_validate('#SermonSeriesName')"
                    )
                );
                ?>
            }
        }).focus(function() {
            if (search_series && this.value == "") {
                 $(this).autocomplete("search", '');
            }
        });
    });

    $(function() {
        $("#SermonSpeakerName").autocomplete({
            source: "<?php echo $this->Html->url(
                    array("plugin" => "urg_sermon", 
                          "controller" => "sermons", 
                          "action" => "autocomplete_speaker")); ?>",
            minLength: 0,
            select: function(event, ui) {
                $("#SermonSpeakerName").val(ui.item.value);
            },
            search: function(event, ui) {
                search_speaker = false;
                if ($("#SermonSpeakerName").val().length == 1) {
                    $("#SermonSpeakerName").autocomplete("close");
                    return false;
                }
            },
            close: function(event, ui) {
                search_speaker = true;
                <?php
                $this->Js->get("#SermonSpeakerName");
                echo $this->Js->request(
                        "/urg_sermon/sermons/validate_field/Sermon/display_speaker_name", 
                    array(
                        "update" => "#SermonSpeakerNameError",
                        "async" => true,
                        "data" => '{ value: $("#SermonSpeakerName").val() }',
                        "dataExpression" => true,
                        "complete" => "on_validate('#SermonSpeakerName', XMLHttpRequest, textStatus)",
                        "before" => "loading_validate('#SermonSpeakerName')"
                    )
                );
                ?>
            }
        }).focus(function() {
            if (search_speaker && this.value == "") {
                $(this).autocomplete("search");
            }
        })
        .data("autocomplete")._renderItem = function(ul, item) {
            var pastor_class = item.belongsToChurch ? " class='pastor_item' " : "";
            return $("<li" + pastor_class + "></li>").data("item.autocomplete", item)
                    .append("<a>" + item.label + "</a>" )
                    .appendTo(ul);
        };
    });
<?php echo $this->Html->scriptEnd(); ?>

<?php echo $this->Html->scriptStart(); ?>
    $(function() {
        $("#in-progress").dialog({
            modal: true,
            autoOpen: false
        })
    });

    $("#SermonAddForm").submit(function() {
        error = false;
        scrolled = false;
        $(":input.invalid").each(function(index) {
            if (!scrolled) {
                $('html,body').animate(
                        { scrollTop: $(this).offset().top - 30 }, 
                        { duration: 'fast', easing: 'swing'}
                );
                scrolled = true;
            }
            $(this).effect("highlight", { color: "#FFD4D4" });
            error = true;
        });

        if (error) return false;

        if (image_in_progress || attachment_in_progress) {
            submit_form = true;
            $("#in-progress").dialog("open");
        }

        return !image_in_progress && !attachment_in_progress;
    });

    function image_uploads_completed(event, data) {
        image_in_progress = false;

        $("#in-progress").dialog("close");

        if (submit_form) {
            $("#SermonAddForm").submit();
        }
    }

    function attachment_uploads_completed(event, data) {
        attachment_in_progress = false;

        $("#in-progress").dialog("close");

        if (submit_form) {
            $("#SermonAddForm").submit();
        }
    }
    
    $($(":input").addClass("dirty"));

    $($(":input").change(function(event) {
        $(this).addClass("dirty");
    }));

    function invalidate(dom_id) {
        has_errors = $("#flashMessage").length;
        if (!has_errors || $(dom_id).hasClass("form-error")) {
            $(dom_id).addClass("invalid"); 
        }
    }

    $(function() {
        invalidate("#PostTitle");
        invalidate("#SermonSeriesName");
        invalidate("#SermonSpeakerName");
    });

    $(function() {
        $("#PostDisplayDate").datepicker({
            altField: "#PostFormattedDate",
            altFormat: "yy-mm-dd",
            dateFormat: "MM d, yy"
        });

        $("input:submit").button();
    });

    $(function() {
        $('#PostDisplayTime').timepicker({
            scrollDefaultNow: true,
            timeFormat: 'h:i A'
        });
    });
<?php echo $this->Html->scriptEnd();
$this->Html->css("/urg_post/css/jquery.timepicker.css", null, array("inline"=>false));
$this->Html->css("/urg_sermon/css/urg_sermon.css", null, array("inline"=>false));