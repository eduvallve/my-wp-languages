<?php

function getDataFromPost($post_id) {
    showFunctionFired('getDataFromPost($post_id)');
    $getDataFromPost = "SELECT post_content FROM wp_posts WHERE ID = $post_id";
    $dataFromPost = $GLOBALS['wpdb']->get_results($getDataFromPost);
    $post_content = implode(array_column($dataFromPost, 'post_content'));
    return $post_content;
}

function cleanHtmlTags($post_content) {
    // Extract only plain text areas from page content & store them in an Array
    $post_innerText = array_map(function($text) {
        $innerText = explode(">", $text);
        return count($innerText) === 2 && $innerText[1] !== '' ? $innerText[1] : '' ;
    }, explode("<", $post_content));

    // Remove all blank/empty cells from the Array
    foreach ($post_innerText as $key => $cell) {
        if (trim($cell) === '') {
            unset($post_innerText[$key]);
        }
    }

    // Reset the indexs of the Array. Ex: [2,10,12,16] ==> [0,1,2,3] & return
    return array_values($post_innerText);
}

function getSavedPostTexts($post_id, $isAdmin = false) {
    if ( isset($GLOBALS['cfg']['savedPostTexts']) ) {
        return $GLOBALS['cfg']['savedPostTexts'];
    } else {
        showFunctionFired('getSavedPostTexts($post_id)');
        $table = $GLOBALS['cfg']['table'];

        $defaultLanguage = convertLanguageCodesForDB(getDefaultLanguage());
        $supportedLanguages = array_map(function($lang) {
            return convertLanguageCodesForDB($lang);
        }, getSupportedLanguages());

        $requestedLanguages = $isAdmin ? implode(", ",$supportedLanguages) : $defaultLanguage ;

        $query_getSavedPostTexts = "SELECT $requestedLanguages FROM $table WHERE post_id = $post_id AND track_language = '$defaultLanguage' ORDER BY id ASC";
        // echo $query_getSavedPostTexts.'<hr>';
        $getSavedPostTexts = $GLOBALS['wpdb']->get_results($query_getSavedPostTexts);

        if ($isAdmin) {
            $savedPostTexts = $getSavedPostTexts;
        } else {
            $savedPostTexts = array_column($getSavedPostTexts, $defaultLanguage);
        }

        $GLOBALS['cfg']['savedPostTexts'] = $savedPostTexts;
        return $savedPostTexts;
    }
}

function savePostTexts($post_id, $post_diffTexts) {
    $table = $GLOBALS['cfg']['table'];
    $defaultLanguage = convertLanguageCodesForDB(getDefaultLanguage());
    $query_savePostTexts = "INSERT INTO $table (post_id, track_language, $defaultLanguage) VALUES ";
    $acum = 0;
    foreach ($post_diffTexts as $post_text) {
        $query_savePostTexts .= "($post_id,\"$defaultLanguage\",\"$post_text\")";
        $acum !== count($post_diffTexts) - 1 ? $query_savePostTexts .= ', ' : '' ;
        $acum = $acum + 1;
    }
    $savePostTexts = $GLOBALS['wpdb']->query($GLOBALS['wpdb']-> prepare($query_savePostTexts));
}

function fillDictionaryTableByPost($post_id) {
    /**
     * Get:
     * - All texts from post_content
     * - All saved texts in dictionary regarding that same post
     * Compare them and extract new still-not-saved texts in dictionary DB
     */
    $post_innerText = cleanHtmlTags(getDataFromPost($post_id));
    $post_savedTexts = getSavedPostTexts($post_id);
    $post_diffTexts = array_diff($post_innerText, $post_savedTexts);
    /**
     * If still-not-saved texts found, then save them in dictionary DB
     */
    if (count($post_diffTexts) > 0) {
        savePostTexts($post_id, $post_diffTexts);
    }
}

function fillDictionaryTable() {
    $post_id = get_the_ID();
    fillDictionaryTableByPost($post_id);
}

?>