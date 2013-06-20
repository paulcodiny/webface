function FormFieldFormCollection() {
    var t = this, $entity = $('#entity');
    $entity.find('.collection').each(function() {
        var $collectionHolder = $(this);

        if (!$entity.hasClass('entity-with-groups')) {
            t.addToggleLink($collectionHolder);
        }

        t.addAddLink($collectionHolder);

        $collectionHolder.find('> div').each(function() {
            t.addRemoveLink($(this));
        });
    });
}

FormFieldFormCollection.prototype.addToggleLink = function($collectionHolder) {
    var $toggleLabel = $collectionHolder.prev('label');
    $toggleLabel.html(
        $('<a />', { href: 'javascript:;', class: 'js-link', text: $toggleLabel.text() }).click(function() {
            $collectionHolder.toggle();
        })
    );
};

FormFieldFormCollection.prototype.addAddLink = function($collectionHolder) {
    var t = this, $addLink = $('<a />', { href: "javascript:;", class: 'js-link', text: 'Добавить' });
    $collectionHolder.append($addLink);

    $addLink.on('click', function(e) {
        e.preventDefault();

        t.addFormCollection($collectionHolder, $(this));
    });
};

FormFieldFormCollection.prototype.addFormCollection = function($collectionHolder, $addLink) {
    var prototype = $collectionHolder.data('prototype'),
        newIndex  = $collectionHolder.find('> div').length,
        newForm   = prototype.replace(/__name__label__/g, newIndex).replace(/__name__/g, newIndex);

    $addLink.before(newForm);
};

FormFieldFormCollection.prototype.addRemoveLink = function($form) {
    var t = this, $removeLink = $('<a />', { href: "javascript:;", class: 'js-link', text: 'Удалить' });
    $form.append($removeLink);

    $removeLink.on('click', function(e) {
        e.preventDefault();

        t.removeFormCollection($form, $(this))
    });
};

FormFieldFormCollection.prototype.removeFormCollection = function($form, $removeLink) {
    var $id = $removeLink.prev('div[id]').find('input[name*=id]');
    if ($id.val()) {
        $.post($id.data('delete-path').replace('0', $id.val()), function() {
            $form.remove();
        });
    }
};

function Filter() {
    this.$filterApplyLink = $('#filter-apply');
    this.$filterApplyLink.on('click', $.proxy(this.applyClickHandler, this));
}

Filter.prototype.applyClickHandler = function() {
    var request = {};
    $('#list-filter').find('select, input, textarea').each(function() {
        var $el = $(this), val = '';
        if (val = $el.val()) {
            if ($el.is(':checkbox')) {
                if ($el.is(':checked')) {
                    request[$el.attr('name')] = val;
                }
            } else {
                request[$el.attr('name')] = val;
            }
        }
    });

    this.$filterApplyLink.attr('href', this.$filterApplyLink.attr('href') + '?' + $.param(request));
};

function ListInlineEdit() {
    var t = this,
        savingTimeout = null;

    $('#list-inline-edit').find('.list-entity').each(function() {
        var $entityRow = $(this);
        $entityRow.find('input, select, :checkbox').each(function() {
            $(this).on('change', function() {
                if (savingTimeout) {
                    window.clearTimeout(savingTimeout);
                }
                $entityRow.addClass('warning');

                // ждём полсекунды
                savingTimeout = window.setTimeout(function() {
                    // если нет никаких новых изменений сохраняем
                    t.save($entityRow);
                }, 500);
            });
        });
    });
}

ListInlineEdit.prototype.save = function($entityRow) {
    var data = $entityRow.find('input, select, :checkbox').serialize(),
        uncheckedCheckboxes = $entityRow.find(':checkbox:not(:checked)').map(function() {
            return encodeURI(this.name + '=0');
        }).get();

    if (uncheckedCheckboxes) {
        data += '&' + uncheckedCheckboxes;
    }

    $.post($entityRow.find('.link-edit').attr('href'), data, function(response) {
        $entityRow.removeClass('warning');
    });
};

function Checker() {
    $('.link-check-all').click(function() {
        $(this).closest('div[id]').find(':checkbox').prop('checked', 'checked').attr('checked', 'checked');
    });

    $('.link-uncheck-all').click(function() {
        $(this).closest('div[id]').find(':checkbox').prop('checked', false).attr('checked', false);
    });

    $('.grouped-choice-index').click(function() {
        $(this).closest('.grouped-choice-index-label').next('.grouped-choice-nested').toggle();
        return false;
    });

    $('.grouped-choice-index-checkbox').change(function() {
        var state = $(this).is(':checked');
        $(this)
            .closest('.grouped-choice-index-label')
            .next('.grouped-choice-nested')
            .find(':checkbox')
            .prop('checked', state)
            .attr('checked', state)
        ;
    });
}

jQuery(function($) {
    var checker = new Checker();
});