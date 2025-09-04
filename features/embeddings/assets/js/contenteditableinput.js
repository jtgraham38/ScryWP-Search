class ContentEditableInput {
    //this class binds the innertext of a contenteditable element to the value of an input
    constructor(el) {
        this.init(el);
    }

    //initialize the class, creating the hidden input and binding the events
    init(el) {
        //link this instance to the element
        this.el = el;
        this.el.setAttribute('contenteditable', 'true');

        //create the hidden input
        this.input = document.createElement('input');
        this.input.type = 'hidden';
        this.input.name = this.el.getAttribute('name');
        this.el.appendChild(this.input);

        //add event listener to this.el to update the input
        this.el.addEventListener('input', () => { this.bind(this) });   //pass in the context, due to event listener
        this.bind();
    }

    //update the input value based on the element's innerText
    bind(context) {
        //bind the input to the element
        if (!context) context = this;
        this.input.value = context.el.innerText;
    }
}

//attach a form element to elements with the contenteditableinput attribute
document.addEventListener('DOMContentLoaded', function () {
    let elements = document.querySelectorAll('[contenteditableinput]');
    elements.forEach(el => {
        let cei = new ContentEditableInput(el);
    });
});