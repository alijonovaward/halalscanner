<!DOCTYPE html>
<html lang="ru">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Проверка товаров и предварительный AI-разбор всей этикетки.">

        <title>HalalScan — проверка продукта</title>

        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body
        data-verdict-url-template="{{ route('api.products.verdict', ['barcode' => '__BARCODE__']) }}"
        data-recognition-url="{{ route('api.ingredient-image-recognitions.store') }}"
        data-analysis-url="{{ route('api.ingredient-analyses.store') }}"
    >
        <header class="site-header">
            <a class="brand" href="/" aria-label="HalalScan — главная">
                <span class="brand-mark" aria-hidden="true">
                    <svg viewBox="0 0 32 32" role="img">
                        <path d="M25.7 4.5C17.2 5 9.8 8.7 6.9 15.2c-2.2 4.8.1 9.8 4.6 11.5 4.7 1.8 9.8-.8 11.2-5.5 1.4-4.9.2-9.7 3-16.7Z" />
                        <path d="M8.3 25.6c3.3-7.6 7.3-12.6 13-16.3" />
                    </svg>
                </span>
                <span>HalalScan</span>
            </a>
        </header>

        <main class="scanner-page">
            <section class="intro" aria-labelledby="page-title">
                <h1 id="page-title">Проверьте продукт</h1>
                <p>Введите штрихкод или сфотографируйте всю этикетку товара.</p>
            </section>

            <section class="barcode-panel">
                <form id="barcode-form" novalidate>
                    <label id="barcode-heading" for="barcode">Штрихкод</label>
                    <div class="barcode-controls">
                        <div class="input-with-icon">
                            <svg aria-hidden="true" viewBox="0 0 24 24">
                                <path d="M4 5v14M7 5v14M11 5v14M14 5v14M17 5v14M20 5v14" />
                            </svg>
                            <input
                                id="barcode"
                                name="barcode"
                                type="text"
                                inputmode="numeric"
                                autocomplete="off"
                                placeholder="Например, 4780042470202"
                                maxlength="32"
                                required
                            >
                        </div>
                        <div class="barcode-actions">
                            <button id="start-barcode-scanner" class="secondary-button" type="button">
                                <svg aria-hidden="true" viewBox="0 0 24 24">
                                    <path d="M4 8V5h3M17 5h3v3M20 16v3h-3M7 19H4v-3" />
                                    <path d="M8 9v6M11 9v6M14 9v6M17 9v6" />
                                </svg>
                                <span data-button-label>Сканировать камерой</span>
                            </button>
                            <button class="primary-button" type="submit">
                                <svg aria-hidden="true" viewBox="0 0 24 24">
                                    <circle cx="10.5" cy="10.5" r="6.5" />
                                    <path d="m15.5 15.5 5 5" />
                                </svg>
                                <span data-button-label>Проверить</span>
                            </button>
                        </div>
                    </div>
                    <input
                        id="barcode-camera-input"
                        class="visually-hidden-file"
                        type="file"
                        accept="image/*"
                        capture="environment"
                        tabindex="-1"
                        aria-hidden="true"
                    >
                    <div id="barcode-scanner" class="barcode-scanner" hidden>
                        <div class="barcode-scanner__header">
                            <strong>Наведите камеру на штрихкод</strong>
                            <button id="close-barcode-scanner" class="text-button" type="button">Закрыть</button>
                        </div>
                        <div class="barcode-scanner__viewport">
                            <video id="barcode-scanner-video" playsinline muted></video>
                            <span class="barcode-scanner__guide" aria-hidden="true"></span>
                        </div>
                        <p id="barcode-scanner-message" class="recognition-message" aria-live="polite"></p>
                    </div>
                    <p id="barcode-error" class="field-error" role="alert" hidden></p>
                </form>
            </section>

            <section id="catalog-result" class="catalog-result" aria-live="polite" hidden>
                <div class="catalog-result__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24">
                        <path d="M4 4h11v13H4z" />
                        <path d="M7 8h5M7 11h3" />
                        <circle cx="16.5" cy="16.5" r="3.5" />
                        <path d="m19 19 2 2" />
                    </svg>
                </div>
                <div>
                    <h2 id="catalog-result-title"></h2>
                    <p id="catalog-result-text"></p>
                </div>
            </section>

            <div class="analysis-workspace">
                <section class="ingredient-panel">
                    <form id="ingredient-form" novalidate>
                        <div class="section-title">
                            <svg aria-hidden="true" viewBox="0 0 24 24">
                                <path d="M7 3h7l4 4v14H7z" />
                                <path d="M14 3v5h5M10 12h5M10 16h5" />
                            </svg>
                            <label id="ingredients-heading" for="ingredients-text">Текст всей этикетки</label>
                        </div>
                        <div class="image-recognition">
                            <div class="image-upload">
                                <span class="image-upload__icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24">
                                        <path d="M4 8.5h3l1.5-2h7l1.5 2h3v10H4z" />
                                        <circle cx="12" cy="13.5" r="3.2" />
                                    </svg>
                                </span>
                                <span>
                                    <strong class="image-upload__title">Загрузить фото всей этикетки</strong>
                                    <span class="image-upload__hint">Определим товар, прочитаем этикетку и проверим её автоматически. Большие фото уменьшим автоматически</span>
                                </span>
                                <div class="image-upload__actions">
                                    <label class="image-upload__choice" for="ingredient-camera">
                                        <input
                                            id="ingredient-camera"
                                            class="image-upload__input"
                                            name="camera_image"
                                            type="file"
                                            accept="image/*"
                                            capture="environment"
                                        >
                                        <svg aria-hidden="true" viewBox="0 0 24 24">
                                            <path d="M4 8.5h3l1.5-2h7l1.5 2h3v10H4z" />
                                            <circle cx="12" cy="13.5" r="3.2" />
                                        </svg>
                                        <span>Снять фото</span>
                                    </label>
                                    <label class="image-upload__choice" for="ingredient-image">
                                        <input
                                            id="ingredient-image"
                                            class="image-upload__input"
                                            name="gallery_image"
                                            type="file"
                                            accept="image/jpeg,image/png,image/webp"
                                        >
                                        <svg aria-hidden="true" viewBox="0 0 24 24">
                                            <rect x="3.5" y="5" width="17" height="14" rx="2" />
                                            <circle cx="9" cy="10" r="1.5" />
                                            <path d="m5.5 17 4.5-4 3 2.5 2.5-2 3 3.5" />
                                        </svg>
                                        <span>Выбрать из галереи</span>
                                    </label>
                                </div>
                            </div>

                            <div id="image-preview" class="image-preview" hidden>
                                <img id="image-preview-picture" alt="Предпросмотр фотографии этикетки">
                                <div class="image-preview__details">
                                    <strong id="image-file-name"></strong>
                                    <button id="remove-image" class="text-button" type="button">Удалить фото</button>
                                </div>
                            </div>

                            <p id="image-error" class="field-error" role="alert" hidden></p>
                            <p id="recognition-message" class="recognition-message" aria-live="polite" hidden></p>
                        </div>
                        <textarea
                            id="ingredients-text"
                            name="label_text"
                            rows="7"
                            minlength="3"
                            maxlength="10000"
                            placeholder="Проверьте распознанный текст всей этикетки"
                            required
                        ></textarea>
                        <p class="privacy-note">
                            <svg aria-hidden="true" viewBox="0 0 24 24">
                                <rect x="5" y="10" width="14" height="10" rx="2" />
                                <path d="M8 10V7a4 4 0 0 1 8 0v3" />
                            </svg>
                            Текст этикетки используется только для анализа.
                        </p>
                        <p id="ingredient-error" class="field-error" role="alert" hidden></p>
                        <button class="primary-button primary-button--wide" type="submit">
                            <svg aria-hidden="true" viewBox="0 0 24 24">
                                <path d="m12 3 .8 2.2L15 6l-2.2.8L12 9l-.8-2.2L9 6l2.2-.8L12 3ZM5 12l1.1 2.9L9 16l-2.9 1.1L5 20l-1.1-2.9L1 16l2.9-1.1L5 12ZM17 11l1.4 3.6L22 16l-3.6 1.4L17 21l-1.4-3.6L12 16l3.6-1.4L17 11Z" />
                            </svg>
                            <span data-button-label>Проверить исправленный текст</span>
                        </button>
                    </form>
                </section>

                <section id="analysis-panel" class="analysis-panel" aria-live="polite" aria-labelledby="analysis-title">
                    <div id="analysis-empty" class="analysis-empty">
                        <span class="analysis-empty__icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24">
                                <path d="M12 3v3M12 18v3M3 12h3M18 12h3M5.6 5.6l2.1 2.1M16.3 16.3l2.1 2.1M18.4 5.6l-2.1 2.1M7.7 16.3l-2.1 2.1" />
                                <circle cx="12" cy="12" r="3" />
                            </svg>
                        </span>
                        <h2 id="analysis-title">Результат появится здесь</h2>
                        <p>Gemini определит товар, прочитает этикетку и выделит признаки, требующие внимания.</p>
                    </div>

                    <div id="analysis-result" hidden>
                        <div class="analysis-heading">
                            <span id="analysis-status-icon" class="status-icon" aria-hidden="true">!</span>
                            <div>
                                <h2 id="analysis-status"></h2>
                                <p id="analysis-summary"></p>
                            </div>
                        </div>
                        <div id="attention-section">
                            <h3>Обнаруженные признаки</h3>
                            <ul id="attention-list" class="attention-list"></ul>
                        </div>
                    </div>

                    <div class="advisory-note">
                        <svg aria-hidden="true" viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="9" />
                            <path d="M12 11v6M12 7.5v.5" />
                        </svg>
                        <p>Предварительный AI-разбор. Это не halal-вердикт.</p>
                    </div>
                </section>
            </div>
        </main>

        <noscript>
            <p class="noscript-note">Для проверки продукта включите JavaScript.</p>
        </noscript>
    </body>
</html>
