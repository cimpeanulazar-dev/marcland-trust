document.addEventListener('DOMContentLoaded', () => {
    const addButton = document.getElementById('marclandtrust-add-item');
    const itemsList = document.getElementById('marclandtrust-items');

    /**
     * Fallback in case no button or list exist in DOM
     * */
    if(!addButton || !itemsList) {
        return;
    }

    /**
     * Add onclick for addButton
     */
    addButton.addEventListener('click', () => {
        addTrustSignal(itemsList);
    });
});

/**
 * Appends a new editable trust signal item to the given list.
 *
 * @param {HTMLUListElement} itemsList - List containing trust signal items.
 * @returns {void}
 */

/**
 * Appends a new editable trust signal to the list.
 *
 * @param {HTMLUListElement} itemsList
 * @returns {void}
 */
function addTrustSignal(itemsList) {
    const item = document.createElement('li');
    item.className = 'marclandtrust-item form-group';

    const label = document.createElement('label');
    label.textContent = 'Trust Signal Text';

    const input = document.createElement('input');
    input.type = 'text';
    input.name = 'trust_signals['+getNameIndex()+']';
    input.placeholder = 'e.g. Free shipping';
    input.className = 'form-control trust-signal-input';

    // Associate the label with its input.
    const inputId = `marclandtrust-text-${crypto.randomUUID()}`;
    input.id = inputId;
    label.htmlFor = inputId;
    console.log(getNameIndex())
    item.appendChild(label);
    item.appendChild(input);

    itemsList.appendChild(item);
}

function getNameIndex(){
    let list = Array.from(document.getElementsByClassName('trust-signal-input'));
    let indexes = list.map(input =>
        Number(input.name.split('[')[1].replace(']', ''))
    );

    if (list.length > 0){
        console.log(indexes);
        return Math.max(0, ...indexes)+1;
    }else{
        return 1;
    }
}