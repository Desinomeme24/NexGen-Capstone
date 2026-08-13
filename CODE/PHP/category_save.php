<?php
session_start();
require_once("config.php");
require_once("tenant_helper.php");

if(!isset($_SESSION['user_id'])){header('Location: /NexGen/CODE/PHP/index.php');exit();}
if(($_SESSION['role']??'')!=='owner'){$_SESSION['inventory_error']='Only owners can add categories.';header('Location: /NexGen/CODE/PHP/inventory_management.php');exit();}
if($_SERVER['REQUEST_METHOD']!=='POST'){header('Location: /NexGen/CODE/PHP/inventory_management.php');exit();}
if(!validateCsrfToken('inventory_category', $_POST['csrf_token'] ?? null)){$_SESSION['inventory_error']='Your session expired. Please try again.';header('Location: /NexGen/CODE/PHP/inventory_management.php');exit();}
$businessId=nxRequireBusinessId($conn);$name=trim($_POST['category_name']??'');
if($name===''){$_SESSION['inventory_error']='Category name is required.';header('Location: /NexGen/CODE/PHP/inventory_management.php');exit();}
$stmt=$conn->prepare('INSERT INTO categories (business_id,category_name) VALUES (?,?)');$stmt->bind_param('is',$businessId,$name);
if($stmt->execute())$_SESSION['inventory_success']='Category added successfully.';else $_SESSION['inventory_error']=$stmt->errno===1062?'Category already exists in your business.':'Failed to add category.';
$stmt->close();header('Location: /NexGen/CODE/PHP/inventory_management.php');exit();
?>