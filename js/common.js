/*
 * Common functions used by eLabFTW
 * http://www.elabftw.net
 */

// for editXP/DB, ctrl-shift-D will add the date
function addDateOnCursor() {
    var todayDate = new Date();
    var year = todayDate.getFullYear();
    // we use +1 on the month because january is 0
    var month = todayDate.getMonth() + 1;
    // we want to have two digits on the month
    if (month < 10) {
        month = "0" + month;
    }
    var day = todayDate.getDate();
    // we want to have two digits on the day
    if (day < 10) {
        day = "0" + day;
    }

    tinyMCE.activeEditor.execCommand('mceInsertContent', false, year + "-" + month + "-" + day + " ");
}

// notifications
function notif(text, cssClass) {
    var htmlText = '<p>' + text + '</p>';
    if (cssClass === 'ok') {
        overlayClass = 'overlay-ok';
    } else {
        overlayClass = 'overlay-ko';
    }
    var overlay = document.createElement('div');
       overlay.setAttribute('id','overlay');
       overlay.setAttribute('class', 'overlay ' + overlayClass);
       // show the overlay
       document.body.appendChild(overlay);
       // add text inside
       document.getElementById('overlay').innerHTML = htmlText;
       // wait a bit and make it disappear
       window.setTimeout(removeNotif, 2733);
}

function removeNotif() {
    $('#overlay').fadeOut(763, function() {
        $(this).remove();
    });
}


/* for menus on team, admin, sysconfig and ucp */

/* parse the $_GET from the url */
function getGetParameters() {
    var prmstr = window.location.search.substr(1);
    return prmstr !== null && prmstr !== "" ? transformToAssocArray(prmstr) : {};
}

/* put the $_GET in array */
function transformToAssocArray( prmstr ) {
    var params = {};
    var prmarr = prmstr.split("&");
    for ( var i = 0; i < prmarr.length; i++) {
        var tmparr = prmarr[i].split("=");
                params[tmparr[0]] = tmparr[1];
            }
    return params;
}
/* to check if the param is good */
function isInt(n) {
    return n % 1 === 0;
}

// show/hide the todolist
function toggleTodoList() {
    todoList = $('#todoList');
    if (todoList.css('display') === 'none') {
        todoList.css('display', 'inline');
    } else {
        todoList.css('display', 'none');
    }
}

// display mol files
function showMol(molFileContent) {
    // the first parameter is a random id
    // otherwise several .mol files will clash
    var viewer = new ChemDoodle.ViewerCanvas(Math.random(), 100, 100);
    viewer.specs.bonds_width_2D = 0.6;
    viewer.specs.bonds_saturationWidth_2D = 0.18;
    viewer.specs.bonds_hashSpacing_2D = 2.5;
    viewer.specs.atoms_font_size_2D = 10;
    viewer.specs.atoms_font_families_2D = ['Helvetica', 'Arial', 'sans-serif'];
    viewer.specs.atoms_displayTerminalCarbonLabels_2D = true;
    var mol = ChemDoodle.readMOL(molFileContent);
    viewer.loadMolecule(mol);
}

// go to url
function go_url(x) {
    if (x === '') {
        return;
    }
    window.location = x;
}

// ENTITY
function toggleLock(type, id) {
    $.post("app/controllers/EntityController.php", {
        lock: true,
        type: type,
        id: id
    }).done(function(data) {
        var json = JSON.parse(data);
        if (json.res) {
            notif(json.msg, 'ok');
            // change the lock icon
            current = $('#lock').attr('src');
            if (current === 'img/lock-gray.png') {
                $('#lock').attr('src', 'img/unlock.png');
            } else {
                $('#lock').attr('src', 'img/lock-gray.png');
            }
        } else {
            notif(json.msg, 'ko');
        }
    });
}

// called when you click the save button of tinymce
function quickSave(type, id) {
    $.post('app/controllers/EntityController.php', {
        quickSave: true,
        type : type,
        id : id,
        // we need this to get the updated content
        title : document.getElementById('title_input').value,
        date : document.getElementById('datepicker').value,
        body : tinymce.activeEditor.getContent()
    }).done(function(data) {
        var json = JSON.parse(data);
        if (json.res) {
            notif(json.msg, 'ok');
        } else {
            notif(json.msg, 'ko');
        }
    });
}


// EXPERIMENTS
// ===========

// VISIBILITY
function updateVisibility(item, visibility) {
    $.post("app/controllers/ExperimentsController.php", {
        updateVisibility: true,
        id: item,
        visibility: visibility
    }).done(function(data) {
        var json = JSON.parse(data);
        if (json.res) {
            notif(json.msg, 'ok');
        } else {
            notif(json.msg, 'ko');
        }
    });
}

// STATUS
function updateStatus(item, status) {
    $.post("app/controllers/ExperimentsController.php", {
        updateStatus: true,
        id: item,
        status : status
    }).done(function(data) {
        var json = JSON.parse(data);
        if (json.res) {
            notif(json.msg, 'ok');
            // change the color of the item border
            // we first remove any status class
            $("#main_section").css('border', null);
            // and we add our new border color
            // first : get what is the color of the new status
            css = '6px solid #' + json.color;
            $("#main_section").css('border-left', css);
        } else {
            notif(json.msg, 'ko');
        }
    });
}

// CREATE TAG
function createTag(e, item_id, type) { // the argument here is the event (needed to detect which key is pressed)
    var keynum;
    if (e.which) {
        keynum = e.which;
    }
    if (keynum == 13) { // if the key that was pressed was Enter (ascii code 13)
        // get tag
        tag = $('#createTagInput').val();
        // POST request
        $.post('app/controllers/EntityController.php', {
            createTag: true,
            createTagTag: tag,
            createTagId: item_id,
            createTagType: type
        }).done(function () {
            if (type === 'experiments') {
                $('#tags_div').load('experiments.php?mode=edit&id=' + item_id + ' #tags_div');
            } else {
                $('#tags_div').load('database.php?mode=edit&id=' + item_id + ' #tags_div');
            }
            // clear input field
            $('#createTagInput').val('');
        });
    } // end if key is enter
}
// DESTROY TAG
function destroyTag(type, item, tag){
    var you_sure = confirm('Delete this?');
    if (you_sure) {
        $.post('app/controllers/EntityController.php', {
            destroyTag: true,
            type:type,
            item:item,
            id:tag,
        })
        .success(function() {
            if (type === 'items') {
                type = 'database';
            }
            $('#tags_div').load(type + '.php?mode=edit&id=' + item + ' #tags_div');
        });
    }
    return false;
}

// CREATE LINK
function experimentsCreateLink(e, item) { // the argument here is the event (needed to detect which key is pressed)
    var keynum;
    if (e.which) {
        keynum = e.which;
    }
    if (keynum == 13) { // if the key that was pressed was Enter (ascii code 13)
        // get link
        link = decodeURIComponent($('#linkinput').val());
        // fix for user pressing enter with no input
        if (link.length > 0) {
            // parseint will get the id, and not the rest (in case there is number in title)
            link = parseInt(link, 10);
            if (!isNaN(link)) {

                $.post('app/controllers/ExperimentsController.php', {
                    createLink: true,
                    id: item,
                    linkId: link
                })
                // reload the link list
                .done(function () {
                    $("#links_div").load("experiments.php?mode=edit&id=" + item + " #links_div");
                    // clear input field
                    $("#linkinput").val("");
                    return false;
                });
            } // end if input is bad
        } // end if input < 0
    } // end if key is enter
}

// DESTROY LINK
function experimentsDestroyLink(link, item, confirmText) {
    var youSure = confirm(confirmText);
    if (youSure === true) {
        $.post('app/controllers/ExperimentsController.php', {
            destroyLink: true,
            id: item,
            linkId: link
        }).done(function (data) {
            var json = JSON.parse(data);
            if (json.res) {
                notif(json.msg, 'ok');
                $("#links_div").load("experiments.php?mode=edit&id=" + item + " #links_div");
            } else {
                notif(json.msg, 'ko');
            }
        });
    }
    return false;
}

// TIMESTAMP
function timestamp(id, confirmText) {
    var youSure = confirm(confirmText);
    if (youSure === true) {
        $.post('app/controllers/ExperimentsController.php', {
            timestamp: true,
            id: id
        }).done(function (data) {
            var json = JSON.parse(data);
            if (json.res) {
                window.location.replace("experiments.php?mode=view&id=" + id);
            } else {
                notif(json.msg, 'ko');
            }
        });
    }
    return false;
}

// COMMENTS
function commentsCreateButtonDivShow() {
    $('#commentsCreateButtonDiv').show();
}

// create
function commentsCreate(id) {
    document.getElementById('commentsCreateButton').disabled = true;
    comment = $('#commentsCreateArea').val();
    // check length
    if (comment.length < 2) {
        notif('Comment too short!');
        document.getElementById('commentsCreateButton').disabled = false;
        return false;
    }

    $.post('app/controllers/CommentsController.php', {
        commentsCreate: true,
        comment: comment,
        id: id
    }).done(function(data) {
        var json = JSON.parse(data);
        if (json.res) {
            notif(json.msg, 'ok');
            $('#expcomment_container').load("experiments.php?mode=view&id=" + id + " #expcomment");
        } else {
            notif(json.msg, 'ko');
        }
    });
}

// destroy
function commentsDestroy(id, expId, confirmText) {
    var you_sure = confirm(confirmText);
    if (you_sure === true) {
        $.post('app/controllers/CommentsController.php', {
            commentsDestroy: true,
            id: id
        }).done(function(data) {
            var json = JSON.parse(data);
            if (json.res) {
                notif(json.msg, 'ok');
                 $('#expcomment_container').load("experiments.php?mode=view&id=" + expId + " #expcomment");
            } else {
                notif(json.msg, 'ko');
            }
        });
    } else {
        return false;
    }
}

// EXPERIMENTS
function experimentsDestroy(id, confirmText) {
    var you_sure = confirm(confirmText);
    if (you_sure !== true) {
        return false;
    }
    $.post('app/controllers/ExperimentsController.php', {
        destroy: true,
        id: id
    }).done(function(data) {
        var json = JSON.parse(data);
        if (json.res) {
            notif(json.msg, 'ok');
            window.location.replace('experiments.php');
        } else {
            notif(json.msg, 'ko');
        }
    });
}

// DATABASE
function databaseDestroy(id, confirmText) {
    var you_sure = confirm(confirmText);
    if (you_sure !== true) {
        return false;
    }
    $.post('app/controllers/DatabaseController.php', {
        destroy: true,
        id: id
    }).done(function(data) {
        var json = JSON.parse(data);
        if (json.res) {
            notif(json.msg, 'ok');
            window.location.replace('database.php');
        } else {
            notif(json.msg, 'ko');
        }
    });
}

// admin.php
// =========

// STATUS
function statusCreate() {
    name = $('#statusName').val();
    color = $('#statusColor').val();
    $.post('app/controllers/StatusController.php', {
        statusCreate: true,
        name: name,
        color: color
    }).done(function(data) {
        var json = JSON.parse(data);
        if (json.res) {
            notif(json.msg, 'ok');
            window.location.replace('admin.php?tab=3');
        } else {
            notif(json.msg, 'ko');
        }
    });
}

function statusUpdate(id) {
    name = $('#statusName_' + id).val();
    color = $('#statusColor_' + id).val();
    isDefault = $('#statusDefault_' + id).is(':checked');

    $.post('app/controllers/StatusController.php', {
        statusUpdate: true,
        id: id,
        name: name,
        color: color,
        isDefault: isDefault
    }).done(function(data) {
        var json = JSON.parse(data);
        if (json.res) {
            notif(json.msg, 'ok');
        } else {
            notif(json.msg, 'ko');
        }
    });
}

function statusDestroy(id) {
    $.post('app/controllers/StatusController.php', {
        statusDestroy: true,
        id: id
    }).done(function(data) {
        var json = JSON.parse(data);
        if (json.res) {
            notif(json.msg, 'ok');
            $('#status_' + id).hide();
        } else {
            notif(json.msg, 'ko');
        }
    });
}


// ITEMS TYPES
function itemsTypesCreate() {
    name = $('#itemsTypesName').val();
    color = $('#itemsTypesColor').val();
    checkbox = $('#itemsTypesBookable').is(":checked");
    if (checkbox) {
        bookable = 1;
    } else {
        bookable = 0;
    }
    template = tinymce.get('itemsTypesTemplate').getContent();
    $.post('app/controllers/ItemsTypesController.php', {
        itemsTypesCreate: true,
        name: name,
        color: color,
        bookable: bookable,
        template: template
    }).done(function(data) {
        var json = JSON.parse(data);
        if (json.res) {
            notif(json.msg, 'ok');
            window.location.replace('admin.php?tab=4');
        } else {
            notif(json.msg, 'ko');
        }
    });
}

function itemsTypesShowEditor(id) {
    $('#itemsTypesEditor_' + id).toggle();
}

function itemsTypesUpdate(id) {
    name = $('#itemsTypesName_' + id).val();
    color = $('#itemsTypesColor_' + id).val();
    checkbox = $('#itemsTypesBookable_' + id).is(":checked");
    if (checkbox) {
        bookable = 1;
    } else {
        bookable = 0;
    }
    template = tinymce.get('itemsTypesTemplate_' + id).getContent();
    $.post('app/controllers/ItemsTypesController.php', {
        itemsTypesUpdate: true,
        id: id,
        name: name,
        color: color,
        bookable: bookable,
        template: template
    }).done(function(data) {
        var json = JSON.parse(data);
        if (json.res) {
            notif(json.msg, 'ok');
        } else {
            notif(json.msg, 'ko');
        }
    });
}

function itemsTypesDestroy(id) {
    $.post('app/controllers/ItemsTypesController.php', {
        itemsTypesDestroy: true,
        id: id
    }).done(function(data) {
        var json = JSON.parse(data);
        if (json.res) {
            notif(json.msg, 'ok');
            $('#itemstypes_' + id).hide();
        } else {
            notif(json.msg, 'ko');
        }
    });
}


// COMMON TEMPLATE
function commonTplUpdate() {
    template = tinymce.get('commonTplTemplate').getContent();
    $.post('app/controllers/AdminController.php', {
        commonTplUpdate: template
    }).done(function() {
        notif('Saved', 'ok');
    });
}

// TEAM GROUP
function teamGroupCreate() {
    var name = $('#teamGroupCreate').val();
    if (name.length > 0) {
        $.post('app/controllers/TeamGroupsController.php', {
            teamGroupCreate: name
        }).done(function() {
            $('#team_groups_div').load('admin.php #team_groups_div');
            $('#teamGroupCreate').val('');
        });
    }
}

function teamGroupUpdate(action) {
    if (action === 'add') {
        user = $('#teamGroupUserAdd').val();
        group = $('#teamGroupGroupAdd').val();
    } else {
        user = $('#teamGroupUserRm').val();
        group = $('#teamGroupGroupRm').val();
    }
    $.post('app/controllers/TeamGroupsController.php', {
        teamGroupUpdate: true,
        action: action,
        teamGroupUser: user,
        teamGroupGroup: group
    }).done(function() {
        $('#team_groups_div').load('admin.php #team_groups_div');
    });
}

function teamGroupDestroy(groupid, confirmText) {
    var you_sure = confirm(confirmText);
    if (you_sure === true) {
        $.post('app/controllers/TeamGroupsController.php', {
            teamGroupDestroy: true,
            teamGroupGroup: groupid
        }).done(function() {
            $("#team_groups_div").load("admin.php #team_groups_div");
        });
    }
    return false;
}
// END TEAM GROUP

// used on import csv/zip to go to next step
function goNext(x) {
    if(x === '') {
        return;
    }
    document.cookie = 'itemType='+x;
    $('.import_block').show();
}

// sysconfig.php
// =============

// PROMOTE SYSADMIN
function promoteSysadmin() {
    // disable button on click
    document.getElementById('promoteSysadminButton').disabled = true;
    email = $('#promoteSysadmin').val();
    $.post('app/controllers/SysconfigController.php', {
        promoteSysadmin: true,
        email: email
    }).done(function(data) {
        var json = JSON.parse(data);
        if (json.res) {
            notif(json.msg, 'ok');
            $('#teamsDiv').load('sysconfig.php #teamsDiv');
        } else {
            notif(json.msg, 'ko');
        }
    });
}

// TEAMS
function teamsCreate() {
    // disable button on click
    document.getElementById('teamsCreateButton').disabled = true;
    name = $('#teamsName').val();
    $.post('app/controllers/SysconfigController.php', {
        teamsCreate: true,
        teamsName: name
    }).done(function(data) {
        var json = JSON.parse(data);
        if (json.res) {
            notif(json.msg, 'ok');
            $('#teamsDiv').load('sysconfig.php #teamsDiv');
        } else {
            notif(json.msg, 'ko');
        }
    });
}

function teamsUpdate(id) {
    document.getElementById('teamsUpdateButton_' + id).disabled = true;
    name = $('#team_' + id).val();
    $.post("app/controllers/SysconfigController.php", {
        teamsUpdate: true,
        teamsUpdateId : id,
        teamsUpdateName : name
    }).done(function(data) {
        var json = JSON.parse(data);
        if (json.res) {
            notif(json.msg, 'ok');
            $('#teamsDiv').load('sysconfig.php #teamsDiv');
        } else {
            notif(json.msg, 'ko');
        }
    });
}

function teamsDestroy(id) {
    // disable button on click
    document.getElementById('teamsDestroyButton_' + id).disabled = true;
    $.post("app/controllers/SysconfigController.php", {
        teamsDestroy: true,
        teamsDestroyId: id
    }).done(function(data) {
        var json = JSON.parse(data);
        if (json.res) {
            notif(json.msg, 'ok');
            $('#teamsDiv').load('sysconfig.php #teamsDiv');
        } else {
            notif(json.msg, 'ko');
        }
    });
}

function teamsUpdateButtonEnable(id) {
    button = document.getElementById('teamsUpdateButton_' + id).disabled = false;
}

function teamsArchive(id) {
    // disable button on click
    document.getElementById('teamsArchiveButton_' + id).disabled = true;
    $.post("app/controllers/SysconfigController.php", {
        teamsArchive: true,
        teamsArchiveId: id
    }).done(function(data) {
        notif('Feature not yet implemented :)', 'ok');
        document.getElementById('teamsArchiveButton_' + id).disabled = false;
    });
}

// send a mass email to all users
function massSend() {
    $.post("app/controllers/SysconfigController.php", {
        massEmail: true,
        subject: $('#massSubject').val(),
        body: $('#massBody').val()
    }).done(function(data) {
        var json = JSON.parse(data);
        if (json.res) {
            notif(json.msg, 'ok');
        } else {
            notif(json.msg, 'ko');
        }
    });
}

// called when mail_method selector is changed; enables/disables the config for the selected/unselected method
function toggleMailMethod(value) {
    if (value == 'sendmail') {
        $('#smtp_config').hide();
        $('#sendmail_config').show();
    } else if (value == 'smtp') {
        $('#smtp_config').show();
        $('#sendmail_config').hide();
    } else if (value == 'php') {
        $('#smtp_config').hide();
        $('#sendmail_config').hide();
        $('#general_mail_config').show();
    } else {
        $('#smtp_config').hide();
        $('#sendmail_config').hide();
        $('#general_mail_config').hide();
    }
}

// send a test email to provided adress
function testemailSend() {
    email = $('#testemailEmail').val();
    // check the email loosely
    if (/\S+@\S+\.\S+/.test(email)) {
        document.getElementById('testemailButton').disabled = true;
        $.post('app/controllers/SysconfigController.php', {
            testemailSend: true,
            testemailEmail: email
        }).done(function(data) {
            var json = JSON.parse(data);
            if (json.res) {
                notif(json.msg, 'ok');
                document.getElementById('testemailButton').disabled = false;
            } else {
                notif(json.msg, 'ko');
            }
        });
    } else {
        notif('Email address looks weird', 'ko');
    }
}

// LOGS
function logsDestroy() {
    // disable button on click
    document.getElementById('logsDestroyButton').disabled = true;
    $.post('app/controllers/SysconfigController.php', {
        logsDestroy: true
    }).done(function(data) {
        var json = JSON.parse(data);
        if (json.res) {
            notif(json.msg, 'ok');
            $('#logsDiv').load('sysconfig.php #logsDiv');
        } else {
            notif(json.msg, 'ko');
        }
    });
}
// EDIT COMMENT ON UPLOAD
function makeEditableFileComment() {
    $('.thumbnail p.editable').editable(function(value, settings) {
        $.post('app/controllers/EntityController.php', {
            updateFileComment : true,
            comment : value,
            id : $(this).attr('id')
        }).done(function(data) {
            var json = JSON.parse(data);
            if (json.res) {
                notif(json.msg, 'ok');
            } else {
                notif(json.msg, 'ko');
            }
        });

        return(value);
        }, {
     tooltip : 'Click to edit',
     indicator : 'Saving...',
     name : 'fileComment',
     submit : 'Save',
     cancel : 'Cancel',
     style : 'display:inline'
    });
}

// UPLOADS DESTROY
function uploadsDestroy(id, type, item_id, confirmText) {
    var youSure = confirm(confirmText);
    if (youSure === true) {
        $.post('app/controllers/EntityController.php', {
            uploadsDestroy: true,
            id: id,
            item_id: item_id,
            type: type
        }).done(function(data) {
            var json = JSON.parse(data);
            if (json.res) {
                notif(json.msg, 'ok');
                if (type === 'items') {
                    type = 'database';
                }
                $("#filesdiv").load(type + ".php?mode=edit&id=" + item_id + " #filesdiv");
            } else {
                notif(json.msg, 'ko');
            }
        });
    }
}

// STAR RATINGS
function updateRating(rating, id) {
    $.post('app/controllers/DatabaseController.php', {
        rating: rating,
        id: id
    }).done(function(data) {
        var json = JSON.parse(data);
        if (json.res) {
            notif(json.msg, 'ok');
        } else {
            notif(json.msg, 'ko');
        }
    });
}

// SEARCH PAGE
function insertParamAndReload(key, value) {
    key = escape(key); value = escape(value);

    var kvp = document.location.search.substr(1).split('&');
    if (kvp === '') {
        document.location.search = '?' + key + '=' + value;
    } else {

        var i = kvp.length; var x; while (i--) {
            x = kvp[i].split('=');

            if (x[0] == key) {
                x[1] = value;
                kvp[i] = x.join('=');
                break;
            }
        }

        if (i < 0) { kvp[kvp.length] = [key, value].join('='); }

        // reload the page
        document.location.search = kvp.join('&');
    }
}

// UCP

// TEMPLATES DESTROY
function templatesDestroy(id) {
    var you_sure = confirm('Delete this ?');
    if (you_sure === true) {
        $.post('app/controllers/UcpController.php', {
            templatesDestroy: true,
            id: id
        }).done(function(data) {
            var json = JSON.parse(data);
            if (json.res) {
                notif(json.msg, 'ok');
                window.location.replace('ucp.php?tab=3');
            } else {
                notif(json.msg, 'ko');
            }
        });
    } else {
        return false;
    }
}

// for importing user template
function readFile(file, onLoadCallback){
    // check for the various File API support
    if (!window.FileReader) {
        alert('Please use a modern web browser. Import aborted.');
        return false;
    }
    var reader = new FileReader();
    reader.onload = onLoadCallback;
    reader.readAsText(file);
}

function exportTpl(name, id) {
    // we have the name of the template used for filename
    // and we have the id of the editor to get the content from
    // we don't use activeEditor because it requires a click inside the editing area
    var content = tinyMCE.get(id).getContent();
    var blob = new Blob([content], {type: "text/plain;charset=utf-8"});
    saveAs(blob, name + ".elabftw.tpl");
}

// parse the query from url
// from  http://stackoverflow.com/a/1099670
function getQueryParams(qs) {
    qs = qs.split('+').join(' ');

    var params = {},
    tokens,
    re = /[?&]?([^=]+)=([^&]*)/g;

    while ((tokens = re.exec(qs)) !== null) {
        params[decodeURIComponent(tokens[1])] = decodeURIComponent(tokens[2]);
    }

    return params;
}
