/**
 * @author Nicolas CARPi <nico-git@deltablot.email>
 * @copyright 2012 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */
import 'jquery-jeditable/src/jquery.jeditable.js';
import Todolist from './Todolist.class';
import i18next from 'i18next';

document.addEventListener('DOMContentLoaded', () => {
  if (!document.getElementById('info')) {
    return;
  }

  const pagesWithoutTodo = ['login', 'register', 'change-pass'];
  if (pagesWithoutTodo.includes(document.getElementById('info').dataset.page)) {
    return;
  }

  const TodolistC = new Todolist();

  // UPDATE TODOITEM
  $(document).on('mouseenter', '.todoItem', ev => {
    ($(ev.currentTarget) as any).editable(input => {
      TodolistC.update(
        ev.currentTarget.dataset.todoitemid,
        input,
      );
      return (input);
    }, {
      tooltip : i18next.t('click-to-edit'),
      indicator : 'Saving...',
      onblur: 'submit',
      style : 'display:inline',
    });
  });

  // to avoid duplicating code between listeners (keydown and click on add)
  function createTodoitem(): void {
    const todoInput = (document.getElementById('todo') as HTMLInputElement);
    const content = todoInput.value;
    if (!content) { return; }

    TodolistC.create(content).then(json => {
      if (json.res) {
        // reload the todolist
        TodolistC.read();
        // and clear the input
        todoInput.value = '';
      }
    });
  }

  // save todo on enter
  document.getElementById('todo').addEventListener('keydown', (event: KeyboardEvent) => {
    if (event.keyCode === 13) {
      createTodoitem();
    }
  });

  // Add click listener and do action based on which element is clicked
  document.getElementById('container').addEventListener('click', event => {
    const el = (event.target as HTMLElement);
    // CREATE TODOITEM
    if (el.matches('[data-action="create-todoitem"]')) {
      createTodoitem();

    // DESTROY TODOITEM
    } else if (el.matches('[data-action="destroy-todoitem"]')) {
      if (confirm(i18next.t('generic-delete-warning'))) {
        const todoitemId = parseInt(el.dataset.todoitemid);
        TodolistC.destroy(todoitemId).then(json => {
          if (json.res) {
            // hide item
            $('#todoItem_' + todoitemId).css('background', '#29AEB9').toggle('blind');
          }
        });
      }

    // TOGGLE TODOITEM
    } else if (el.matches('[data-action="toggle-todolist"]')) {
      TodolistC.toggle();

    // TOGGLE SUBLISTS i.e. actual todo-list and unfinished item/experiment steps
    } else if (el.matches('[data-action="toggle-next"]')) {
      const sublist = el.nextElementSibling.id + '-isClosed';
      if (!localStorage.getItem(sublist)) {
        localStorage.setItem(sublist, '1');
      } else if (localStorage.getItem(sublist) === '1') {
        localStorage.removeItem(sublist);
      }
    }
  });
});
