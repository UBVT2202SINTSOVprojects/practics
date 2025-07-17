<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

$pageTitle = "Помочь приюту | " . SITE_NAME;
require_once 'includes/header.php';
?>

<div class="help-container">
    <h1>Помочь приюту WoofMeow</h1>
    <p class="intro-text">Ваша поддержка помогает нам заботиться о животных и находить им любящих хозяев. Любая помощь важна!</p>
    
    <div class="help-sections">
        <!-- Секция с необходимыми материалами -->
        <section class="materials-section">
            <div class="section-header">
                <i class="fas fa-box-open"></i>
                <h2>Необходимые материалы</h2>
            </div>
            
            <div class="materials-grid">
                <div class="material-card">
                    <div class="material-icon" style="background-color: #FFD166;">
                        <i class="fas fa-bone"></i>
                    </div>
                    <h3>Корма</h3>
                    <ul>
                        <li>Сухой корм для собак (все возрасты)</li>
                        <li>Сухой корм для кошек (все возрасты)</li>
                        <li>Консервы для кошек и собак</li>
                        <li>Корм для щенков и котят</li>
                        <li>Лакомства для дрессировки</li>
                    </ul>
                </div>
                
                <div class="material-card">
                    <div class="material-icon" style="background-color: #06D6A0;">
                        <i class="fas fa-pills"></i>
                    </div>
                    <h3>Медикаменты</h3>
                    <ul>
                        <li>Антипаразитарные препараты</li>
                        <li>Перевязочные материалы</li>
                        <li>Антибиотики широкого спектра</li>
                        <li>Витаминные комплексы</li>
                        <li>Средства для ухода за шерстью</li>
                    </ul>
                </div>
                
                <div class="material-card">
                    <div class="material-icon" style="background-color: #118AB2;">
                        <i class="fas fa-home"></i>
                    </div>
                    <h3>Хозяйственные товары</h3>
                    <ul>
                        <li>Одноразовые пеленки</li>
                        <li>Наполнитель для кошачьих туалетов</li>
                        <li>Миски, поводки, ошейники</li>
                        <li>Лежаки и домики</li>
                        <li>Игрушки для животных</li>
                    </ul>
                </div>
            </div>
            
            <div class="delivery-info">
                <h3><i class="fas fa-truck"></i> Как передать материалы?</h3>
                <p>Вы можете привезти необходимые вещи в приют в часы работы. Пожалуйста, заранее позвоните по телефону <a href="tel:+74951234567">+7 (495) 123-45-67</a>, чтобы согласовать время визита.</p>
                <p>Также мы сотрудничаем с интернет-магазинами - вы можете заказать доставку прямо в приют.</p>
            </div>
        </section>
        
        <!-- Секция с пожертвованиями -->
        <section class="donation-section">
            <div class="section-header">
                <i class="fas fa-donate"></i>
                <h2>Финансовая помощь</h2>
            </div>
            
            <div class="donation-methods">
                <div class="method-card">
                    <div class="method-icon">
                        <i class="fas fa-university"></i>
                    </div>
                    <h3>Банковский перевод</h3>
                    <div class="bank-details">
                        <p><strong>Банк:</strong> Тинькофф Банк</p>
                        <p><strong>Реквизиты:</strong></p>
                        <p>ИНН 7710140679</p>
                        <p>КПП 771001001</p>
                        <p>БИК 044525974</p>
                        <p><strong>Номер счета:</strong> 40703810200000000001</p>
                        <p><strong>Назначение платежа:</strong> Благотворительное пожертвование</p>
                    </div>
                </div>
                
                                <div class="method-card">
                <div class="method-card">
                    <div class="method-icon">
                        <i class="fab fa-cc-paypal"></i>
                    </div>
                    <h3>Онлайн-платежи</h3>
                    <div class="online-methods">
                        <a href="#" class="payment-method">
                            <img src="<?php echo SITE_URL; ?>/assets/images/payment/paypal.png" alt="PayPal">
                        </a>
                        <a href="#" class="payment-method">
                            <img src="<?php echo SITE_URL; ?>/assets/images/payment/yoomoney.png" alt="ЮMoney">
                        </a>
                        <a href="#" class="payment-method">
                            <img src="<?php echo SITE_URL; ?>/assets/images/payment/sbp.png" alt="СБП">
                        </a>
                    </div>
                    <p>Вы можете сделать разовое пожертвование или оформить ежемесячную подписку.</p>
                </div>
            </div>

            
            <div class="donation-info">
                <h3><i class="fas fa-info-circle"></i> О финансовой помощи</h3>
                <p>Все пожертвования идут на:</p>
                <ul>
                    <li>Лечение и вакцинацию животных</li>
                    <li>Покупку кормов и медикаментов</li>
                    <li>Содержание приюта и коммунальные платежи</li>
                    <li>Организацию мероприятий по поиску хозяев</li>
                </ul>
                <p>Мы публикуем финансовые отчеты каждый квартал в нашей <a href="reports.php">специальном разделе</a>.</p>
            </div>
        </section>
        
        <!-- Секция волонтерства -->
        <section class="volunteer-section">
            <div class="section-header">
                <i class="fas fa-hands-helping"></i>
                <h2>Стать волонтером</h2>
            </div>
            
            <div class="volunteer-content">
                <p>Если вы хотите помогать на регулярной основе, рассмотрите возможность стать волонтером приюта.</p>
                <div class="volunteer-options">
                    <div class="option-card">
                        <i class="fas fa-dog"></i>
                        <h4>Выгул собак</h4>
                        <p>Нашим собакам нужны регулярные прогулки и социализация</p>
                    </div>
                    <div class="option-card">
                        <i class="fas fa-cat"></i>
                        <h4>Уход за кошками</h4>
                        <p>Помощь в уборке, кормлении и социализации кошек</p>
                    </div>
                    <div class="option-card">
                        <i class="fas fa-camera"></i>
                        <h4>Фотосъемка</h4>
                        <p>Создание качественных фото животных для сайта</p>
                    </div>
                </div>
                <a href="volunteer.php" class="btn btn-primary">Заполнить анкету волонтера</a>
            </div>
        </section>
    </div>
</div>

<?php
require_once 'includes/footer.php';
?>