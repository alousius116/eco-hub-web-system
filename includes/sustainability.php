<?php
// /includes/sustainability.php

function getEcoLevel($co2){
  if ($co2 >= 50) return "Eco Champion";
  if ($co2 >= 20) return "Eco Hero";
  if ($co2 >= 5)  return "Eco Starter";
  return "New User";
}
