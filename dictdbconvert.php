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
      $new_dict = array();

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

        $existing_kanji_entry_keys = array_keys(array_column($new_dict, 'kanji'), $kanji);
        if ( !count($existing_kanji_entry_keys) ) { 
          // If there isn't already a match for this kanji
          $new_dict[] = $entry;
        } else {
          // If there IS already a match for this kanji
          foreach ( $existing_kanji_entry_keys as $existing_kanji_entry_key ) {
            $existing_kanji_entry = $new_dict[$existing_kanji_entry_key];
            $kanji_diff = @array_diff($entry, $existing_kanji_entry);
            $diff_count = count($kanji_diff);
            // If there is only one difference, it will be the kana
            // All definitions for a kanji/kana pair will be in that entry (ie. difference won't be one definition but same kanji/kana pair).
            if ( 1 < $diff_count ) {
              // This is a new kana/definition pair
              $new_dict[] = $entry;
            } elseif ( 1 == $diff_count ) {
              $existing_kana = $existing_kanji_entry['kana'];
              if ( !is_array($existing_kana) ) {
                $new_kana = array($existing_kana, $kana);
              } else {
                $new_kana = $existing_kana;
                $new_kana[] = $kana;
              }

              // Add new kana array to new_dict
              $new_dict[$existing_kanji_entry_key]['kana'] = $new_kana;
            }
          }
        }
      }

      echo "<p>Total entries: " . count( $new_dict ) . "</p>";
      mysqli_close($link);
    ?>
  </body>
</html>
