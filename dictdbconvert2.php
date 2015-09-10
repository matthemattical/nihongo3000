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

      set_time_limit(0);

      if ($result = mysqli_query($link, "SELECT $columns FROM dictionary")) {
        while ($entry = mysqli_fetch_assoc($result)) {
          $kanji = $entry['kanji'];
          $kana = $entry['kana'];
          $def01 = $entry['def01'];

          $existing_kanji_entry_keys = array_keys(array_column($new_dict, 'kanji'), $kanji);
          if ( !count($existing_kanji_entry_keys) ) { 
            // If there isn't already a match for this kanji
            $new_dict[] = $entry;
            $values = "";
            foreach ( $entry as $key => $value ) {
              $value = mysqli_real_escape_string($link, $value);
              $values .= "'$value', ";
            }
            $values = rtrim($values, ", ");

            $sql = "INSERT INTO newdict ($columns) VALUES($values)";
            if ( !mysqli_query($link, $sql) ) {
              echo "<p>ERROR: " . mysqli_error($link) . "</p>";
            }
          } else {
            // If there IS already a match for this kanji
            foreach ( $existing_kanji_entry_keys as $existing_kanji_entry_key ) {
              $existing_kanji_entry = $new_dict[$existing_kanji_entry_key];
              if ( $existing_kanji_entry['def01'] != $def01 ) {
              /*
              $kanji_diff = @array_diff($entry, $existing_kanji_entry);
              $diff_count = count($kanji_diff);
              // If there is only one difference, it will be the kana
              // All definitions for a kanji/kana pair will be in that entry (ie. difference won't be one definition but same kanji/kana pair).
              if ( 1 < $diff_count ) {
              */
                // This is a new kana/definition pair
                $new_dict[] = $entry;
              } else {
                $existing_kana = $existing_kanji_entry['kana'];
                $kana = mysqli_real_escape_string($link, $kana);
                if ( !is_array($existing_kana) ) {
                  $new_kana = array($existing_kana, $kana);
                } else {
                  $new_kana = $existing_kana;
                  $new_kana[] = $kana;
                }

                // Add new kana array to new_dict
                $new_dict[$existing_kanji_entry_key]['kana'] = $new_kana;

                $kana_serial = serialize($new_kana);

                $sql = "UPDATE newdict SET kana = '$kana_serial' WHERE kanji = '$kanji' AND def01 = '$def01'";
                if ( !mysqli_query($link, $sql) ) {
                  echo "<p>ERROR: " . mysqli_error($link) . "</p>";
                }
              }
            }
          }
        }
        mysqli_free_result($result);
      }


      echo "<p>Total entries: " . count( $new_dict ) . "</p>";
      mysqli_close($link);
    ?>
  </body>
</html>
