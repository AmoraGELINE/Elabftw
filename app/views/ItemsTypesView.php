<?php
/**
 * \Elabftw\Elabftw\ItemsTypesView
 *
 * @author Nicolas CARPi <nicolas.carpi@curie.fr>
 * @copyright 2012 Nicolas CARPi
 * @see http://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */
namespace Elabftw\Elabftw;

/**
 * The kind of items you can have in the database for a team
 */
class ItemsTypesView
{
    /** instance of ItemsTypes */
    public $itemsTypes;

    /**
     * Constructor
     *
     * @param ItemsTypes $itemsTypes
     */
    public function __construct(ItemsTypes $itemsTypes)
    {
        $this->itemsTypes = $itemsTypes;
    }

    /**
     * Output html for create new item type
     *
     * @return string $html
     */
    public function showCreate()
    {
        $html = "<h3>" . _('Add a new type of item') . "</h3>";
        $html .= "<ul class='list-group'><li class='list-group-item'>";
        $html .= "<ul class='list-inline'>";
        $html .= "<li>" . _('Name') . " <input type='text' id='itemsTypesName' /></li>";
        $html .= "<li>" . _('Color') . " <input class='colorpicker' type='text' id='itemsTypesColor' value='29AEB9' /></li>";
        $html .= "<li>" . _('Bookable') . " <input type='checkbox' id='itemsTypesBookable'><span class='smallgray'>" . sprintf(_("Will be selectable in the %sscheduler%s"), "<a href='team.php'>", "</a>") . "</span></li></ul>";

        $html .= "<textarea class='mceditable' id='itemsTypesTemplate' /></textarea>";
        $html .= "<div class='submitButtonDiv'><button onClick='itemsTypesCreate()' class='button'>" . _('Save') . "</button></div>";
        $html .= "</li></ul>";

        return $html;

    }

    /**
     * List the items types
     *
     * @return string $html
     */
    public function show()
    {
        $itemsTypesArr = $this->itemsTypes->readAll();

        $html = "<h3>" . _('Database Items Types') . "</h3>";
        $html .= "<ul class='draggable sortable_itemstypes list-group'>";

        foreach ($itemsTypesArr as $itemType) {

            $html .= "<li id='itemstypes_" . $itemType['id'] . "' class='list-group-item center'>";

            $html .= "<ul class='list-inline'>";

            $html .= "<li>" . _('Name') . " <input type='text' id='itemsTypesName_" . $itemType['id'] . "' value='" . $itemType['name'] . "' /></li>";
            $html .= "<li style='color:#" . $itemType['bgcolor'] . "'>" . _('Color') . " <input class='colorpicker' type='text' style='display:inline' id='itemsTypesColor_" . $itemType['id'] . "' value='" . $itemType['bgcolor'] . "' /></li>";
            $html .= "<li>" . _('Bookable') . " <input id='itemsTypesBookable_" . $itemType['id'] . "' type='checkbox' ";
            if ($itemType['bookable']) {
                $html .= 'checked ';
            }
            $html .= "></li>";
            $html .= "<li><button onClick='itemsTypesShowEditor(" . $itemType['id'] . ")' class='button button-neutral'>" . _('Edit the template') . "</button></li>";
            $html .= "<li><button onClick='itemsTypesUpdate(" . $itemType['id'] . ")' class='button'>" . _('Save') . "</button></li>";
            $html .= "<li><button class='button button-delete' onClick=\"itemsTypesDestroy(" . $itemType['id'] . ")\">";
            $html .= _('Delete') . "</button></li>";

            $html .= "</li>";
            $html .= "<li class='itemsTypesEditor' id='itemsTypesEditor_" . $itemType['id'] . "'><textarea class='mceditable' style='height:50px' id='itemsTypesTemplate_" . $itemType['id'] . "' />" . $itemType['template'] . "</textarea></li>";
            $html .= "</ul>";
        }
        $html .= "</ul>";

        return $html;
    }
}
