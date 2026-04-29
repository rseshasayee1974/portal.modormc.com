const fs = require('fs');
const path = require('path');

function replaceInFile(filePath) {
    let content = fs.readFileSync(filePath, 'utf8');
    let original = content;

    // Replace InputNumber
    content = content.replace(/<InputNumber/g, '<BaseInputNumber');
    content = content.replace(/<\/InputNumber>/g, '</BaseInputNumber>');
    content = content.replace(/import\s+InputNumber\s+from\s+['"].*?(?:BaseInputNumber\.vue|primevue\/inputnumber)['"];?/g, "import BaseInputNumber from '@/Components/Base/BaseInputNumber.vue';");

    // Replace Select
    content = content.replace(/<Select(\s|>|\/)/g, '<BaseSelect$1');
    content = content.replace(/<\/Select>/g, '</BaseSelect>');
    content = content.replace(/import\s+Select\s+from\s+['"]primevue\/select['"];?/g, "import BaseSelect from '@/Components/Base/BaseSelect.vue';");
    
    // Replace InputText
    content = content.replace(/<InputText(\s|>|\/)/g, '<BaseInput$1');
    content = content.replace(/<\/InputText>/g, '</BaseInput>');
    content = content.replace(/import\s+InputText\s+from\s+['"]primevue\/inputtext['"];?/g, "import BaseInput from '@/Components/Base/BaseInput.vue';");
    
    // Replace Inputtext (lowercase t if any)
    content = content.replace(/<Inputtext(\s|>|\/)/g, '<BaseInput$1');
    content = content.replace(/<\/Inputtext>/g, '</BaseInput>');
    content = content.replace(/import\s+Inputtext\s+from\s+['"]primevue\/inputtext['"];?/g, "import BaseInput from '@/Components/Base/BaseInput.vue';");

    if (content !== original) {
        fs.writeFileSync(filePath, content, 'utf8');
        console.log('Updated:', filePath);
    }
}

function processDirectory(dir) {
    const files = fs.readdirSync(dir);
    
    for (const file of files) {
        if (dir.includes('Base') && dir.endsWith('Base')) continue; // skip Base component folder
        const fullPath = path.join(dir, file);
        if (fs.statSync(fullPath).isDirectory()) {
            if (file === 'Base') continue;
            processDirectory(fullPath);
        } else if (fullPath.endsWith('.vue')) {
            replaceInFile(fullPath);
        }
    }
}

processDirectory('c:/wamp64/www/v4_modomines.com/resources/js/Pages');
processDirectory('c:/wamp64/www/v4_modomines.com/resources/js/Components');
console.log('Done');
