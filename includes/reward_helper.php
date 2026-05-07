<?php
/* =====================================================
   Reward Helper (SECURE / PREPARED)
   Used by profile.php, reward UI, etc.
===================================================== */

function getUserSustainability(mysqli $conn, int $user_id): array
{
  // ensure record exists
  $sqlInit = "
    INSERT IGNORE INTO user_sustainability (user_id, eco_points, total_co2, eco_level)
    VALUES (?, 0, 0, 'New User')
  ";
  $st = mysqli_prepare($conn, $sqlInit);
  mysqli_stmt_bind_param($st, "i", $user_id);
  mysqli_stmt_execute($st);

  // fetch data
  $sql = "
    SELECT eco_points, total_co2, eco_level
    FROM user_sustainability
    WHERE user_id = ?
    LIMIT 1
  ";
  $stmt = mysqli_prepare($conn, $sql);
  mysqli_stmt_bind_param($stmt, "i", $user_id);
  mysqli_stmt_execute($stmt);
  $res = mysqli_stmt_get_result($stmt);

  return $res && ($row = mysqli_fetch_assoc($res)) ? $row : [
    'eco_points' => 0,
    'total_co2'  => 0,
    'eco_level'  => 'New User'
  ];
}

/* -----------------------------------------------------
   Update sustainability points + CO2
----------------------------------------------------- */
function addSustainability(
  mysqli $conn,
  int $user_id,
  int $points,
  float $co2
): void {

  $sql = "
    UPDATE user_sustainability
    SET eco_points = eco_points + ?,
        total_co2  = total_co2  + ?
    WHERE user_id = ?
  ";
  $stmt = mysqli_prepare($conn, $sql);
  mysqli_stmt_bind_param($stmt, "idi", $points, $co2, $user_id);
  mysqli_stmt_execute($stmt);

  updateEcoLevel($conn, $user_id);
}

/* -----------------------------------------------------
   Auto update eco level based on points
----------------------------------------------------- */
function updateEcoLevel(mysqli $conn, int $user_id): void
{
  $sql = "
    SELECT eco_points
    FROM user_sustainability
    WHERE user_id = ?
    LIMIT 1
  ";
  $stmt = mysqli_prepare($conn, $sql);
  mysqli_stmt_bind_param($stmt, "i", $user_id);
  mysqli_stmt_execute($stmt);
  $res = mysqli_stmt_get_result($stmt);
  $row = $res ? mysqli_fetch_assoc($res) : null;

  $pts = (int)($row['eco_points'] ?? 0);

  if ($pts >= 2000)      $level = 'Eco Hero';
  elseif ($pts >= 1000)  $level = 'Eco Champion';
  elseif ($pts >= 300)   $level = 'Eco Saver';
  else                   $level = 'New User';

  $sqlUp = "
    UPDATE user_sustainability
    SET eco_level = ?
    WHERE user_id = ?
  ";
  $stUp = mysqli_prepare($conn, $sqlUp);
  mysqli_stmt_bind_param($stUp, "si", $level, $user_id);
  mysqli_stmt_execute($stUp);
}

/* -----------------------------------------------------
   UI helper: level benefits
----------------------------------------------------- */
function getLevelBenefits(string $level): array
{
  $map = [
    'New User' => [
      'badge' => '🌱',
      'next_level' => 'Eco Saver',
      'next_points' => 300,
      'benefits_list' => [
        'Track your eco impact',
        'Earn eco points from rentals'
      ]
    ],
    'Eco Saver' => [
      'badge' => '♻️',
      'next_level' => 'Eco Champion',
      'next_points' => 1000,
      'benefits_list' => [
        'Bonus eco points',
        'Priority borrowing'
      ]
    ],
    'Eco Champion' => [
      'badge' => '🌍',
      'next_level' => 'Eco Hero',
      'next_points' => 2000,
      'benefits_list' => [
        'Higher rewards',
        'Featured profile badge'
      ]
    ],
    'Eco Hero' => [
      'badge' => '🏆',
      'next_level' => '',
      'next_points' => 0,
      'benefits_list' => [
        'Maximum rewards',
        'Top eco contributor badge'
      ]
    ],
  ];

  return $map[$level] ?? $map['New User'];
}
