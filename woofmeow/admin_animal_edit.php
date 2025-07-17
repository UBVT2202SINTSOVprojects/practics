<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Check permissions
if (!is_logged_in() || !in_array($_SESSION['user_type'], ['admin', 'staff'])) {
    redirect('dashboard.php');
}

$isEditMode = isset($_GET['id']);
$animalId = $isEditMode ? (int)$_GET['id'] : 0;

// Get animal data (if editing)
if ($isEditMode) {
    $stmt = $pdo->prepare("SELECT * FROM animals WHERE id = ?");
    $stmt->execute([$animalId]);
    $animal = $stmt->fetch();
    
    if (!$animal) {
        $_SESSION['error_message'] = "Животное не найдено";
        redirect('admin_animals.php');
    }
}

// Process form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $type = $_POST['type'];
    $gender = $_POST['gender'];
    $breed = trim($_POST['breed'] ?? '');
    $color = trim($_POST['color'] ?? '');
    $ageMonths = (int)$_POST['age_months'];
    $status = $_POST['status'];
    $description = trim($_POST['description'] ?? '');
    $detailedDescription = trim($_POST['detailed_description'] ?? '');
    
    $errors = [];
    
    if (empty($name)) $errors[] = "Не указано имя животного";
    if (!in_array($type, ['cat', 'dog', 'kitten', 'puppy'])) $errors[] = "Неверный тип животного";
    if (!in_array($gender, ['male', 'female'])) $errors[] = "Неверный пол животного";
    if ($ageMonths <= 0) $errors[] = "Возраст должен быть положительным числом";
    if (!in_array($status, ['available', 'sick', 'adopted'])) $errors[] = "Неверный статус животного";
    
    // Handle file upload
    $imagePath = $isEditMode ? ($animal['image_path'] ?? '') : '';
    if (!empty($_FILES['image']['name'])) {
        $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/assets/images/animals/';
        $uploadFile = $uploadDir . basename($_FILES['image']['name']);
        
        // Check if file is an image
        $imageFileType = strtolower(pathinfo($uploadFile, PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (!in_array($imageFileType, $allowedExtensions)) {
            $errors[] = "Разрешены только JPG, JPEG, PNG и GIF файлы";
        } elseif ($_FILES['image']['size'] > 5000000) {
            $errors[] = "Файл слишком большой (максимум 5MB)";
        } else {
            // Generate unique filename
            $newFilename = uniqid() . '.' . $imageFileType;
            $uploadFile = $uploadDir . $newFilename;
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadFile)) {
                $imagePath = $newFilename;
                
                // Delete old image if exists
                if ($isEditMode && !empty($animal['image_path'])) {
                    $oldImage = $uploadDir . $animal['image_path'];
                    if (file_exists($oldImage)) {
                        unlink($oldImage);
                    }
                }
            } else {
                $errors[] = "Ошибка при загрузке файла";
            }
        }
    }
    
    if (empty($errors)) {
        try {
            if ($isEditMode) {
                // Update existing animal
                $stmt = $pdo->prepare("
                    UPDATE animals 
                    SET name = ?,
                        type = ?,
                        gender = ?,
                        breed = ?,
                        color = ?,
                        age_months = ?,
                        status = ?,
                        description = ?,
                        detailed_description = ?,
                        image_path = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $name,
                    $type,
                    $gender,
                    $breed,
                    $color,
                    $ageMonths,
                    $status,
                    $description,
                    $detailedDescription,
                    $imagePath,
                    $animalId
                ]);
                
                $_SESSION['success_message'] = "Данные животного успешно обновлены";
            } else {
                // Create new animal
                $stmt = $pdo->prepare("
                    INSERT INTO animals 
                    (name, type, gender, breed, color, age_months, status, 
                     description, detailed_description, image_path, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $name,
                    $type,
                    $gender,
                    $breed,
                    $color,
                    $ageMonths,
                    $status,
                    $description,
                    $detailedDescription,
                    $imagePath
                ]);
                $animalId = $pdo->lastInsertId();
                
                $_SESSION['success_message'] = "Животное успешно добавлено";
            }
            
            redirect('admin_animals.php');
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Ошибка при сохранении данных: " . $e->getMessage();
        }
    } else {
        $_SESSION['error_message'] = implode("<br>", $errors);
    }
}

$pageTitle = ($isEditMode ? "Редактирование" : "Добавление") . " животного | " . SITE_NAME;
require_once 'includes/header.php';
?>
<link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/admin.css">
<div class="admin-container admin-animal-edit">
    <div class="admin-grid">
        <?php require_once 'admin_sidebar.php'; ?>
        
        <div class="admin-content">
            <h1><?= $isEditMode ? 'Редактирование животного' : 'Добавление нового животного' ?></h1>
            
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert error"><?= $_SESSION['error_message'] ?></div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>
            
            <form method="post" enctype="multipart/form-data" id="animalForm">
                <div class="form-row">
                    <div class="form-group">
                        <label for="name">Имя животного:</label>
                        <input type="text" name="name" id="name" class="form-control" required
                               value="<?= $isEditMode ? htmlspecialchars($animal['name']) : (isset($_POST['name']) ? htmlspecialchars($_POST['name']) : '') ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="type">Тип:</label>
                        <select name="type" id="type" class="form-control" required>
                            <option value="cat" <?= ($isEditMode && $animal['type'] == 'cat') || (isset($_POST['type']) && $_POST['type'] == 'cat' ? 'selected' : '') ?>>Кот</option>
                            <option value="dog" <?= ($isEditMode && $animal['type'] == 'dog') || (isset($_POST['type']) && $_POST['type'] == 'dog' ? 'selected' : '') ?>>Собака</option>
                            <option value="kitten" <?= ($isEditMode && $animal['type'] == 'kitten') || (isset($_POST['type']) && $_POST['type'] == 'kitten' ? 'selected' : '') ?>>Котенок</option>
                            <option value="puppy" <?= ($isEditMode && $animal['type'] == 'puppy') || (isset($_POST['type']) && $_POST['type'] == 'puppy' ? 'selected' : '') ?>>Щенок</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="gender">Пол:</label>
                        <select name="gender" id="gender" class="form-control" required>
                            <option value="male" <?= ($isEditMode && $animal['gender'] == 'male') || (isset($_POST['gender']) && $_POST['gender'] == 'male' ? 'selected' : '')?>>♂ Мужской</option>
                            <option value="female" <?= ($isEditMode && $animal['gender'] == 'female') || (isset($_POST['gender']) && $_POST['gender'] == 'female' ? 'selected' : '') ?>>♀ Женский</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="breed">Порода:</label>
                        <input type="text" name="breed" id="breed" class="form-control"
                               value="<?= $isEditMode ? htmlspecialchars($animal['breed']) : (isset($_POST['breed']) ? htmlspecialchars($_POST['breed']) : '' )?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="color">Окрас:</label>
                        <input type="text" name="color" id="color" class="form-control"
                               value="<?= $isEditMode ? htmlspecialchars($animal['color']) : (isset($_POST['color']) ? htmlspecialchars($_POST['color']) : '' )?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="age_months">Возраст (месяцев):</label>
                        <input type="number" name="age_months" id="age_months" class="form-control" min="1" required
                               value="<?= $isEditMode ? $animal['age_months'] : (isset($_POST['age_months']) ? $_POST['age_months'] : '1' )?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="status">Статус:</label>
                        <select name="status" id="status" class="form-control" required>
                            <option value="available" <?= ($isEditMode && $animal['status'] == 'available') || (isset($_POST['status']) && $_POST['status'] == 'available' ? 'selected' : '') ?>>Доступен</option>
                            <option value="sick" <?= ($isEditMode && $animal['status'] == 'sick') || (isset($_POST['status']) && $_POST['status'] == 'sick' ? 'selected' : '')?>>Болеет</option>
                            <option value="adopted" <?= ($isEditMode && $animal['status'] == 'adopted') || (isset($_POST['status']) && $_POST['status'] == 'adopted' ? 'selected' : '')?>>Усыновлен</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="image">Фото:</label>
                        <input type="file" name="image" id="image" class="form-control" accept="image/*">
                        <?php if ($isEditMode && !empty($animal['image_path'])): ?>
                            <div class="current-image">
                                <p>Текущее фото:</p>
                                <img src="<?= SITE_URL . '/assets/images/animals/' . htmlspecialchars($animal['image_path']) ?>" 
                                     alt="<?= htmlspecialchars($animal['name']) ?>" class="animal-thumb">
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="description">Краткое описание:</label>
                    <textarea name="description" id="description" class="form-control" rows="3"><?= $isEditMode ? htmlspecialchars($animal['description']) : (isset($_POST['description']) ? htmlspecialchars($_POST['description']) : '')?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="detailed_description">Подробное описание:</label>
                    <textarea name="detailed_description" id="detailed_description" class="form-control" rows="5"><?= $isEditMode ? htmlspecialchars($animal['detailed_description']) : (isset($_POST['detailed_description']) ? htmlspecialchars($_POST['detailed_description']) : '') ?></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Сохранить</button>
                    <a href="admin_animals.php" class="btn btn-outline">Отмена</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
require_once 'includes/footer.php';
?>