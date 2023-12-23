// Функция, которая будет вызвана при изменении поля ввода файла
function showSecondElement() {
    // Получаем поле ввода файла
    const fileInput = document.getElementById('excelFile');
    if(!checkExtension(fileInput)) return;
    // Получаем второй элемент (параграф с сообщением)
    const secondElement = document.getElementById('secondElement');
    // Проверяем, выбран ли файл
    if (fileInput.files.length > 0) {
        secondElement.style.display = 'block'; // Показываем второй элемент
    } else {
        secondElement.style.display = 'none'; // Скрываем второй элемент
    }
}

function checkExtension (fileInput) {
    const allowedExtensions = ['.xlsx']; // Разрешенные расширения файлов
    // Получаем имя выбранного файла
    const fileName = fileInput.value;
    // Получаем расширение файла, разделяя имя файла точкой
    const fileExtension = fileName.split('.').pop();
    // Проверяем, соответствует ли расширение одному из разрешенных расширений
    if (!allowedExtensions.includes('.' + fileExtension.toLowerCase())) {
        // Расширение файла не допустимо, очищаем поле ввода файла
        alert('Расширение файла недопустимо: ' + fileExtension);
        fileInput.value = '';
        return false;
    }
    return true;
}

function createAlphabet(letters) {
    const alphabet = [];
    const startCode = letters[0].charCodeAt(0); // Получить код символа 'A'
    const endCode = letters[1].charCodeAt(0);   // Получить код символа 'J'
    for (let i = startCode; i <= endCode; i++) {
        alphabet.push(String.fromCharCode(i)); // Преобразовать код в символ и добавить в массив
    }
    return alphabet;
}

function getOffsetFromA(startDataColumn) {
    startDataColumn = startDataColumn.toUpperCase();

    // Получаем порядковый номер буквы в ASCII
    const charCodeA = 'A'.charCodeAt(0);
    const charCodeLetter = startDataColumn.charCodeAt(0);

    // Рассчитываем разницу
    return charCodeLetter - charCodeA;
}

let offset;

function getTitleRowNum(ws) {
    let titleRowNum;
    const range = ws['!ref'];
    const rangeLetters = range.split(':').map(el => el[0].toUpperCase());
    const rangeDigits = range.split(':').map(el => +el.match(/\d+/g).join(''));
    const alphabet = createAlphabet(rangeLetters);
    if(rangeLetters[0] !== 'A') {
        offset = getOffsetFromA(rangeLetters[0]);
    }
    let key, key1, key2;
    for(let row = rangeDigits[0]; row <= rangeDigits[1]; row++) {
        for(let i = 0; i < alphabet.length; i++) {
            key = alphabet[i] + row;
            key1 = alphabet[i] + (row + 1);
            key2 = alphabet[i] + (row + 2);
            if(!ws[key] || !ws[key1] || !ws[key2]) continue;
            const column = ws[key];
            if(!column || typeof column !== 'object') continue;
            if(column['v'] === models[0]) {
                titleRowNum = row;
                break;
            }
        }
        if(titleRowNum) break;
    }
    return titleRowNum;
}

function getColumnNumbers(data, titleRowNum) {
    let titles;
    for(let i = 0; i < data.length; i++) {
        if(data[i].includes(models[0])) {
            titles = data[i];
            break;
        }
    }
    if(titleRowNum) {
        document.getElementById('titleRowNum').value = titleRowNum;
    }
    const titleNumbers = models.map(model => {
        const pattern = new RegExp(model, "i");
        for(let j = 0; j <= titles.length; j++) {
            if(titles[j] && pattern.test(titles[j])) return offset ? j + 1 + offset : j + 1;
        }
    })

    return titleNumbers;
}

function updateTitleNumbers(event) {
    const file = event.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const data = new Uint8Array(e.target.result);
            const workbook = XLSX.read(data, { type: 'array' });
            const firstSheetName = workbook.SheetNames[0];
            const worksheet = workbook.Sheets[firstSheetName];

            const titleRowNum = getTitleRowNum(worksheet);

            // Теперь есть доступ к данным в виде объекта JavaScript.
            // Например, можно преобразовать их в массив:
            const jsonData = XLSX.utils.sheet_to_json(worksheet, { header: 1 });

            // jsonData теперь содержит данные из файла Excel в виде двумерного массива.
            const titleNumbers = getColumnNumbers(jsonData, titleRowNum);
            if(titleNumbers.length > 0 && titleNumbers.every(value => !isNaN(Number(value)))) {
                updateSelects(titleNumbers);
            }
        };
        reader.readAsArrayBuffer(file);
    }
}

function updateSelects(titleNumbers) {
    const selects = document.querySelectorAll('select');
    const optQty = Math.max.apply(null, titleNumbers)

    selects.forEach((select, i) => {
        const defaultOptQty = select.options.length;
        let count = 1;
        while (select.options.length != optQty) {
            if(defaultOptQty > optQty) select.remove(optQty); // Удаляем лишние опции
            if(defaultOptQty < optQty) {
                const option = document.createElement("option");
                option.text = option.value = `${defaultOptQty + count}`; // Текст и значение опции сеоекта
                select.add(option);
                count++;
            }
        }
        for (let j = 0; j < select.options.length; j++) {
            select.options[j].removeAttribute("selected");
        }
        // Затем устанавливаем атрибут selected для опции с индексом 2
        select.options[titleNumbers[i] - 1].setAttribute("selected", "selected");
    })
}

function showSpinner() {
    spinner.classList.remove("hidden");
}

// Показываем спиннер
const button = document.getElementById("load");
const spinner = document.getElementById("spinner");
button.addEventListener("click", showSpinner);


// Назначаем обработчик события change на поле ввода файла
const fileInput = document.getElementById('excelFile');
fileInput.addEventListener('change', updateTitleNumbers);
fileInput.addEventListener('change', showSecondElement);