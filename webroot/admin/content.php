<?php

require_once __DIR__ . "/../../resources/autoload.php";

use UnityWebPortal\lib\UnityHTTPD;
use UnityWebPortal\lib\UserFlag;

if (!$USER->getFlag(UserFlag::ADMIN)) {
    UnityHTTPD::forbidden("not an admin", "You are not an admin.");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    UnityHTTPD::validatePostCSRFToken();
    if (!empty($_POST["pageSel"]) && isset($_POST["content"])) {
        $SQL->editPage(
            UnityHTTPD::getPostData("pageSel"),
            UnityHTTPD::getPostData("content"),
        );
    }
}

require $LOC_HEADER;
?>

<h1>Page Content Management</h1>
<hr>

<form id="pageForm" method="POST" action="">
    <?php echo UnityHTTPD::getCSRFTokenHiddenFormInput(); ?>
    <select name="pageSel" required aria-label="select page">
        <option value="" selected disabled hidden>Select page...</option>
        <?php
        $pages = $SQL->getPages();

        foreach ($pages as $page) {
            echo "<option value='" . $page["page"] . "'>" . $page["page"] . "</option>";
        }
        ?>
    </select>

    <br><br>

    <textarea name="content" id="editor" form="pageForm"></textarea>

    <br><br>

    <input type="submit" value="Edit Page">
</form>


<script>
    const url = '<?php echo getURL("admin/ajax/get_page_contents.php"); ?>';
    const {Title} = CKEDITOR;
    setupCKEditor(extraPlugins=[Title]).then(mainEditor => {
        $("#pageForm > select[name=pageSel]").change(function(e) {
            $.ajax({
                url: `${url}?pageid=` + $(this).val(),
                dataType: "json",
                success: function(result) {
                    mainEditor.setData(result.content);
                },
                error: function(result) {
                    mainEditor.setData(result.responseText);
                },
            });
        });
    }).catch(error => { console.error(error) });

    $("#pageForm").on("submit", function(e) {
        if (!confirm("Are you sure you want to edit this page?")) {
            e.preventDefault();
        }
    })
</script>

<?php require $LOC_FOOTER; ?>
