<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <title>title</title>
    <link rel="stylesheet" href="style.css">
    <script src="script.js"></script>
  </head>
  <body>
    <?php

      $link = mysqli_connect("localhost", "jmdict", "jmdict", "jmdict");
      $dict = array();
      $common = array();

      $columns = 'kanji, kana, def01, def02, def03, def04, def05, def06, def07, def08, def09, '; 
      for ($x = 10; $x < 53; $x++) {
        $columns .= 'def' . $x;
        if ($x != 52) {
          $columns .= ', ';
        }
      }

      /* check connection */
      if (mysqli_connect_errno()) {
        printf("Connect failed: %s\n", mysqli_connect_error());
        exit();
      }

      mysqli_set_charset($link, "utf8");
      mysqli_select_db($link, 'jmdict');

      $max_kana_kanji = '';
      $max_kana_num = 0;
      $processed_kanji = array();

      if ($result = mysqli_query($link, "SELECT $columns FROM dictionary")) {
        while ($entry = mysqli_fetch_assoc($result)) {
          $dict[] = $entry;
        }
        mysqli_free_result($result);
      }

      set_time_limit(0);

      foreach ($dict as $entry) {
        $kanji = $entry['kanji'];
        $kana = $entry['kana'];
        $processed = 0;

        // see if we've already processed this kanji and this is an already-counted pronunciation
        $proc_kanji_dups = array_keys(array_column($processed_kanji, 'kanji'), $kanji);
        if ( count($proc_kanji_dups) ) {
          foreach ( $proc_kanji_dups as $proc_kanji_key ) {
            // diff the entry and the processed kanji to see if the only difference is the kana
            // i.e., this is merely a different pronunciation
            $proc_kanji_entry = $processed_kanji[$proc_kanji_key];
            $proc_kanji_diff_count = count($proc_kanji_diffs = array_diff($entry, $proc_kanji_entry));
            /*
            echo "<pre>" . print_r($proc_kanji_diffs, 1) . "</pre>";
            echo "diff_count: $proc_kanji_diff_count";
            */
            // if the count is 0 or 1, this kanji has been processed
            if (1 == $proc_kanji_diff_count) {
              $processed = 1;
            }
          }
        }

        if ( 0 == $processed ) {
          $processed_kanji[] = $entry;
          $kanji_dups = array_keys(array_column($dict, 'kanji'), $kanji);
          $kana_keys = 1;
          if ( count($kanji_dups) > 1 ) {
            foreach ($kanji_dups as $kanji_key) {
              $kanji_key_entry = $dict[$kanji_key];
              // diff the entry and the processed kanji to see if the only difference is the kana
              // i.e., this is merely a different pronunciation
              $diff_count = count($diffs = array_diff($entry, $kanji_key_entry));
              if (1 == $diff_count && array_key_exists('kana', $diffs)) {
                $kana_keys++;
              }
            }
          }
          if ($kana_keys > $max_kana_num) {
            $max_kana_kanji = $kanji;
            $max_kana_num = $kana_keys;
          }
          if ( $kana_keys > 1 ) {
            //echo "<p>$kanji has $kana_keys pronunciations</p>";
          }
        }
      }

      echo "<h1>$max_kana_kanji has the most pronunciations with $max_kana_num.</h1>";

      mysqli_close($link);
    ?>
  </body>
</html>
