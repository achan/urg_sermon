<div class="sermons view">
    <?php foreach ($banners as $banner) { ?>
    <div id="banner" class="span9 right-border">
        <?php echo $this->Html->image($banner, array("class"=>"shadow")); ?>
    </div>
    <?php } ?>
    <div id="about-panel" class="span3">
        <?php if ($sermon["Series"]["description"] != "") { ?>
            <h3><?php echo __("About the series") ?></h3>
            <?php echo $sermon["Series"]["description"]; ?>
        <?php } else if ($sermon["Pastor"]["description"] != "") { ?>
            <h3><?php echo __("About the speaker") ?></h3>
            <?php echo $sermon["Pastor"]["description"]; ?>
        <?php } else { ?>
            <h3><?php echo strtoupper(__("About us")); ?></h3>
        <?php } ?>
    </div>

    <?php 
        if (isset($attachments["Audio"])) {
            echo "<div class='span12 sermon-audio'>";
            $playlist = array();
            foreach ($attachments["Audio"] as $filename => $attachment_id) {
                array_push($playlist, array(
                        "title" => $sermon["Post"]["title"],
                        "link" => "/urg_post/audio/" . $sermon["Sermon"]["id"] . "/" . $filename,
                        "id" => "sermon-audio-link-" . $sermon["Sermon"]["id"] . "-player"
                ));
            }
            echo $this->SoundManager2->build_page_player($playlist);
            echo "</div>";
        } else {
            echo "<div id='sermon-title' class='span12'>";
            echo "<div>" . $sermon["Post"]["title"] . "</div>";
            echo "</div>";
        }
    ?>

    <div id="sermon-info" class="span12">
        <div id="sermon-series" 
                class="alpha span3 top-border bottom-border right-border sermon-details" 
                style="border-right-width: 0px">
            <h3 class="sermon-details"><?php echo __("From the series"); ?></h3>
            <?php echo $sermon["Series"]["name"]; ?>
        </div>
        <div id="sermon-date" 
                class="span3 top-border bottom-border right-border left-border sermon-details"
                style="border-right-width: 0px">
            <h3 class="sermon-details"><?php echo __("Taken place on"); ?></h3>
            <?php echo $this->Time->format("F d, Y", $sermon["Post"]["publish_timestamp"]) ?>
        </div>
        <div id="sermon-speaker" 
                class="span3 top-border bottom-border right-border left-border sermon-details"
                style="border-right-width: 0px">
            <h3 class="sermon-details"><?php echo __("Spoken by"); ?></h3>
            <?php echo $sermon["Pastor"]["name"] != "" ? $sermon["Pastor"]["name"] : 
                    $sermon["Sermon"]["speaker_name"] ?>
        </div>
        <div id="sermon-resources" 
                class="omega span3 top-border bottom-border left-border sermon-details">
            <h3 class="sermon-details"><?php echo __("Resources"); ?></h3>
            <?php if (isset($attachments["Documents"])) { ?>
                <ul id="sermon-resource-list">
                <?php foreach ($attachments["Documents"] as $filename=>$attachment_id) { ?> 
                    <li>
                        <?php 
                        $url = $this->Html->url("/urg_post/files/" .  
                                $sermon["Sermon"]["id"] . "/" . $filename); 
                        $image_options = array("style"=>"height: 32px", 
                                               "alt"=>$filename, 
                                               "title"=>$filename); 
                        echo $this->Html->link(
                            $this->Html->image("/urg_sermon/img/icons/" . 
                                    strtolower(substr($filename, strrpos($filename, ".") + 1, 
                                    strlen($filename))) . ".png", $image_options), 
                                    $url, array("escape" => false, "class" => "gdoc") ); 
                        ?>
                    </li>
                <?php } ?>
                <?php if (isset($attachments["Audio"])) { ?>
                    <li>
                        <?php foreach ($attachments["Audio"] as $filename => $attachment_id) {
                            $url = $this->Html->url("/urg_post/audio/" . 
                                    $sermon["Sermon"]["id"] . "/" . $filename);
                            $image_options = array("style"=>"height: 32px",
                                                   "alt"=>$filename,
                                                   "title"=>$filename);
                            echo $this->Html->link(
                                $this->Html->image("/urg_sermon/img/icons/" . 
                                        strtolower(substr($filename, strrpos($filename, ".") + 1, 
                                        strlen($filename))) . ".png", $image_options), $url,
                                        array("escape" => false, "class" => "exclude sermon-audio",
                                        "id" => "sermon-audio-link-" . $sermon["Sermon"]["id"]) );
                        } ?>
                    </li>
                <?php } ?>
                </ul>
            <? } ?>
        </div>
    </div>

    <div id="sermon-meta" class="span5 right-border">
        <?php if ($sermon["Sermon"]["description"] != "") { ?>
        <div class="sermon-description">
            <h2><?php echo __("Description") ?></h2>
            <?php echo $sermon["Sermon"]["description"] ?>
        </div>
        <?php } ?>
        <?php if ($sermon["Sermon"]["passages"] != "") { ?>
        <div class="sermon-passage">
            <h2><?php echo __("Passage") ?></h2>
            <?php echo $sermon["Sermon"]["passages"] . " "; ?>
            <span class="sermon-passage-translation">
                <?php echo $this->Html->link("[ESV]", "/urg_sermon/sermons/passages/" . $this->Bible->encode_passage($sermon["Sermon"]["passages"])); ?>
            </span>
            <div id="sermon-passage-text" style="display: none"></div>
            <div id="sermon-passage-text-loading" style="display: none">
                <?php echo $this->Html->image("/urg_sermon/img/loading.gif"); ?>
            </div>
        </div>
        <?php } ?>
        
        <?php if (isset($sermon["Series"]) && $sermon["Series"]["name"] != "No Series") { ?>
        <div class="series">
            <h2><?php echo $sermon["Series"]["name"] ?></h2>
            <ol class="series-sermon-list">
            <?php 
                $counter = 0;
                foreach ($series_sermons as $series_sermon) {
            ?>
                <li class="series-sermon-list-item <?php echo $counter++ % 2 ? "even" : ""?>">
                    <a href="<?php echo $this->Html->url("/urg_post/posts/view/") . 
                            $series_sermon["Sermon"]["id"] ?>"><?php echo $series_sermon["Post"]["title"]?></a>
                    <div class="series-sermon-details">
                        <?php echo sprintf(__("by %s on %s"),
                                $this->element("speaker_name", array("plugin"=>"urg_sermon", 
                                        "sermon"=> $series_sermon)),
                                $this->Time->format("n/j/y", $sermon['Post']['publish_timestamp'])) ?>
                    </div>
                </li>
            <?php } ?>
            </ol>
        </div>
        <?php } ?>
    </div>

    <div class="span7">
    <?php if (isset($attachments["Documents"])) { ?>
        <div id="sermon-docs" style="display: none">
            <iframe class="shadow sermon-attachment-viewer" id="sermon-doc-viewer"></iframe>
            <a href="#" id="close-sermon-doc"><?php echo $this->Html->image("/urg_sermon/img/icons/x.png", array("style"=>"height: 32px")); ?></a>
        </div>
    <? } ?>
        <div id="sermon-notes">
            <h2><?php echo __("Sermon notes"); ?></h2>
            <?php echo $sermon["Post"]["content"]; ?>
        </div>
    </div>

</div>
<script type="text/javascript">
<?php echo $this->element("js_equal_height"); ?>
$("div.sermon-details").equalHeight();

$(".gdoc").click(function() {
    $("#sermon-doc-viewer").attr("src", "http://docs.google.com/gview?embedded=true&url=http://<?php echo $_SERVER['SERVER_NAME'] ?>" + $(this).attr("href"));
    $("#sermon-notes").hide();
    $("#sermon-docs").show("fade");
    return false;
});

$("#close-sermon-doc").click(function() {
    $("#sermon-docs").hide();
    $("#sermon-notes").show("slide");
    return false;
});

$(".sermon-passage a").click(function() {
    $("#sermon-passage-text-loading").show();
    $("#sermon-passage-text").load($(this).attr("href"),
        function () { 
            $("#sermon-passage-text-loading").hide();
            $(this).show("slide");
        }
    );

    return false;
});

$("#sermon-resource-list li a").click(function() {
    pagePlayer.handleClick({
        target:document.getElementById($(this).attr("id") + "-player")
    });
    return false;
});
</script>

<?php $this->Html->css("/urg_sermon/css/urg_sermon.css", null, array("inline" => false)); ?>
