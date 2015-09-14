<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <title>日本語３０００</title>
    <!-- 
    <link rel="stylesheet" href="style.css">
    <script src="script.js"></script>
    -->
  </head>
  <body>
    <?php

      $dict = array();
      $common = array();
      // We'll be using this to convert all def columns into one value later
      $defs = "";

      $columns = 'kanji, kana, '; 
      $defs_for_sql = 'def01, def02, def03, def04, def05, def06, def07, def08, def09, ';
      for ($x = 10; $x < 53; $x++) {
        $defs_for_sql .= 'def' . $x;
        if ($x != 52) {
          $defs_for_sql .= ', ';
        }
      }
      $columns .= $defs_for_sql;

      $mysqli = new mysqli("localhost", "jmdict", "jmdict", "jmdict");

      /* check connection */
      if ($mysqli->connect_errno) {
        printf("Connect failed: %s\n", $mysqli->connect_error);
        exit();
      }

      /* change character set to utf8 */
      if (!$mysqli->set_charset("utf8")) {
        printf("Error loading character set utf8: %s\n", $mysqli->error);
        exit();
      }

      $mysqli->select_db('jmdict');

      $max_kana_kanji = '';
      $max_kana_num = 0;
      $new_dict = array();

      set_time_limit(0);


      if ( $query_common_word = $mysqli->query("SELECT word FROM common") ) {
        // for ( $x = 0; $x < 100; $x++ ) {
          //$common_word = $query_common_word->fetch_assoc();
        while ( $common_word = $query_common_word->fetch_assoc() ) {
          // echo "common_word: <pre>" . print_r($common_word, 1) . "</pre>";
          $word = $mysqli->real_escape_string($common_word['word']);

          // Grab all instances of this word from dictionary DB, using kanji
          if ($query_dictionary_kanji = $mysqli->query("SELECT $columns FROM dictionary WHERE kanji = '$word'")) {
            while ($dictionary_kanji = $query_dictionary_kanji->fetch_assoc()) {
              // echo "dictionary_kanji: <pre>" . print_r($dictionary_kanji, 1) . "</pre>";
              $kanji = $word;
              $kana = $dictionary_kanji['kana'];

              // Check for (P)... remove from array (doesn't matter for my use)
              // NOTE: (P) marks common definitions... I think...
              if ( $p_loc = array_search('(P)', $dictionary_kanji) ) {
                $dictionary_kanji[$p_loc] = '';
              }

              $defs_array = explode(', ', $defs_for_sql);
              $defs = "";
              foreach ( $defs_array as $def_column ) {
                $defs .= $mysqli->real_escape_string($dictionary_kanji[$def_column]) . "; ";
              }
              $defs = rtrim($defs, "; ");

              $dictionary_kanji = array(
                'kanji' => $kanji,
                'kana' => $kana,
                'def' => $defs,
              );

              echo "<p>kanji: $kanji</p><pre>" . print_r($dictionary_kanji, 1) . "</pre>";

              // Check for any words already in newdict using the same kanji
              if ( $query_newdict_kanji = $mysqli->query("SELECT * FROM newdict WHERE kanji LIKE '%$kanji%'")) {
                if ( $query_newdict_kanji->num_rows != 0 ) {
                  // We'll assume this is new until proven otherwise
                  $is_new = 1;
                  echo "<p>********** matches: " . $query_newdict_kanji->num_rows . " ***********</p>";
                  while ( $newdict_kanji = $query_newdict_kanji->fetch_assoc() ) {
                    if ( $is_new ) {
                      $newdict_key = $newdict_kanji['key'];

                      // Check for (P)... remove from array (doesn't matter for my use)
                      // NOTE: (P) marks common definitions... I think...
                      if ( $p_loc = array_search('(P)', $newdict_kanji) ) {
                        $newdict_kanji[$p_loc] = '';
                      }

                      echo "<p>comparing to: " . $newdict_kanji['kanji'] . " -- " . $newdict_kanji['kana'] . " -- " . $newdict_kanji['def'] . "</p>";
                      echo "<p>kanji: " . $newdict_kanji['kanji'] . "</p><pre>" . print_r($newdict_kanji, 1) . "</pre>";

                      $diffarray = array_diff($dictionary_kanji, $newdict_kanji);
                      echo "<p>------------------- RESULT ------------------</p>";
                      echo "<p>kanji: $kanji</p><pre>" . print_r($diffarray, 1) . "</pre>";
                      echo "<p>----------------- END RESULT ----------------</p>";
                      if ( !empty( $diffarray ) ) {
                        echo "<p>-------------------- THEY ARE DIFFERENT -------------------------</p>";

                        if ( 1 == count($diffarray) && array_key_exists('kana', $diffarray) ) {
                          // Maybe it's only the kana...
                          // It's definitely not new
                          $is_new = 0;
                          echo "<p>-------------------- ONLY THE KANA IS DIFFERENT-------------------------</p>";
                          if ( $newdict_kanji['kana'] != ($kana . "; ") && 0 ===  preg_match("/^$kana\; /", $newdict_kanji['kana']) && 0 ===  preg_match("/ $kana\; /", $newdict_kanji['kana']) ) {
                            echo "<p>-------------------- DIFFERENT KANA, SAME EVERYTHING ELSE -------------------------</p>";
                            // This is not a new word, but it's a new kana for this kanji / def pair
                            $existing_kana = "";
                            // If there is, in fact, already a kana...
                            // (the dictionary db stores the character in the kanji column if there is no kanji...)
                            if ( !empty($newdict_kanji['kana'] ) ) {
                              $existing_kana = $newdict_kanji['kana'];
                            }
                            $kana = $mysqli->real_escape_string($kana);
                            $new_kana = $existing_kana . $kana . "; ";

                            echo "<p>----------------- UPDATING WITH KANA -----------------------</p>";
                            if ( !$mysqli->query("UPDATE newdict SET kana = '$new_kana' WHERE `key` = $newdict_key") ) {
                              printf("Error updating: %s\n", $mysqli->error);
                              exit();
                            }
                          } 
                          echo "<p>-------------------- KANA ALREADY PRESENT - NO CHANGE -------------------------</p>";
                        } else if ( 1 == count($diffarray) && array_key_exists('kanji', $diffarray) ) {
                          // Maybe it's only the kanji...
                          // It's definitely not new
                          $is_new = 0;
                          echo "<p>-------------------- ONLY THE KANJI IS DIFFERENT-------------------------</p>";
                          if ( $newdict_kanji['kanji'] != ($kanji . "; ") && 0 ===  preg_match("/^$kanji\; /", $newdict_kanji['kanji']) && 0 ===  preg_match("/ $kanji\; /", $newdict_kanji['kanji']) ) {
                            echo "<p>-------------------- DIFFERENT KANJI, SAME EVERYTHING ELSE -------------------------</p>";
                            // This is not a new word, but it's a new kanji for this kana / def pair
                            $existing_kanji = "";
                            // If there is, in fact, already a kanji or two...
                            if ( !empty($newdict_kanji['kanji'] ) ) {
                              $existing_kanji = $newdict_kanji['kanji'];
                            }
                            $kanji = $mysqli->real_escape_string($kanji);
                            $new_kanji = $existing_kanji . $kanji . "; ";

                            echo "<p>----------------- UPDATING WITH KANJI -----------------------</p>";
                            if ( !$mysqli->query("UPDATE newdict SET kanji = '$new_kanji' WHERE `key` = $newdict_key") ) {
                              printf("Error updating: %s\n", $mysqli->error);
                              exit();
                            }
                          }
                          echo "<p>-------------------- KANJI ALREADY PRESENT - NO CHANGE -------------------------</p>";
                        } else {
                          echo "<p>-------------------- MORE THAN ONE DIFFERENCE OR DIFFERENCE IN DEFS -------------------------</p>";
                          // There are differences, but they're not only the kana or only the kanji
                          // I guess it's a new word...
                          $is_new = 1;
                        }
                      } else {
                        echo "<p>-------------------- THEY ARE THE SAME -------------------------</p>";
                        $is_new = 0;
                      }
                    }
                  }
                } else {
                  echo "<p>-------------------- COMPLETELY NEW -------------------------</p>";
                  // There is no existing entry in newdict and we should add this
                  // echo "New word, new kanji";
                  $is_new = 1;
                }
              }

              if ( $is_new ) {
                // if it is new, let us add it okay?
                $values = "";
                // serialize those kana no matter what
                if ( ! empty($dictionary_kanji['kana']) ) {
                  $dictionary_kanji['kana'] = $mysqli->real_escape_string($dictionary_kanji['kana']) . "; ";
                }

                $kanji = "";
                $kana = "";
                $defs = "";
                foreach ( $dictionary_kanji as $key => $value ) {
                  switch ($key) {
                    case 'kanji':
                      $kanji = $mysqli->real_escape_string($value);
                      break;
                    case 'kana':
                      $kana = $value;
                      break;
                    default:
                      $defs .= $mysqli->real_escape_string($value);
                  } 
                }
                $values = "'" . $kanji . "', '" . $kana . "', '" . $defs . "'";
                $newdict_columns = 'kanji, kana, def';

                echo "<p>----------------- ADDING NEW KANJI -----------------------</p>";

                if ( !$mysqli->query("INSERT INTO newdict ($newdict_columns) VALUES($values)") ) {
                  printf("Error inserting: %s\n", $mysqli->error);
                  exit();
                }
              }
              $query_newdict_kanji->free();
            }
            $query_dictionary_kanji->free();
          }
        }
        $query_common_word->free();
      }
      $mysqli->close();

      echo "<p>Booyah: ブーヤー</p>";
    ?>
  </body>
</html>
