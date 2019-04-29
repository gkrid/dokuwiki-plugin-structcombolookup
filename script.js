function structcombolookup_change_narrowby(select, disableChild) {
    var $narowingSelect = jQuery(select),
        name = $narowingSelect.data('childName'),
        option = $narowingSelect.children("option:selected").val();
    $form = $narowingSelect.parents("form"),
        $targetSelect = $form.find("[name='" + name + "']");

    //show all
    $targetSelect.find("option").show();
    if (option === '') {
        $targetSelect.find("option").removeAttr("selected");
        $targetSelect.find("option").first().attr("selected", "selected");
        if (disableChild) $targetSelect.attr('disabled', 'disabled');
    } else {
        if (disableChild) $targetSelect.removeAttr('disabled');
        $targetSelect.find("option[data-parent!='"+option+"']").hide();
    }
}

jQuery(function($) {
    return function () {
        $.widget( "custom.structcombolookup", {
            _create: function() {
                this.wrapper = $( "<span>" )
                    .addClass( "custom-structcombolookup" )
                    .insertAfter( this.element );

                this.element.hide();
                this._createAutocomplete();
                this._createShowAllButton();
            },

            _createAutocomplete: function() {
                var selected = this.element.children( ":selected" ),
                    value = selected.val() ? selected.text() : "";

                this.input = $( "<input>" )
                    .appendTo( this.wrapper )
                    .val( value )
                    .attr( "title", "" )
                    .addClass( "custom-structcombolookup-input ui-widget ui-widget-content ui-state-default ui-corner-left" )
                    .autocomplete({
                        delay: 0,
                        minLength: 0,
                        source: $.proxy( this, "_source" )
                    })
                    .tooltip({
                        classes: {
                            "ui-tooltip": "ui-state-highlight"
                        }
                    });

                this._on( this.input, {
                    autocompleteselect: function( event, ui ) {
                        ui.item.option.selected = true;
                        this._trigger( "select", event, {
                            item: ui.item.option
                        });
                    },

                    autocompletechange: "_removeIfInvalid"
                });
            },

            _createShowAllButton: function() {
                var input = this.input,
                    wasOpen = false;

                $( "<a>" )
                    .attr( "tabIndex", -1 )
                    .attr( "title", "Show All Items" )
                    .tooltip()
                    .appendTo( this.wrapper )
                    .button({
                        icons: {
                            primary: "ui-icon-triangle-1-s"
                        },
                        text: false
                    })
                    .removeClass( "ui-corner-all" )
                    .addClass( "custom-structcombolookup-toggle ui-corner-right" )
                    .on( "mousedown", function() {
                        wasOpen = input.autocomplete( "widget" ).is( ":visible" );
                    })
                    .on( "click", function() {
                        input.trigger( "focus" );

                        // Close if already visible
                        if ( wasOpen ) {
                            return;
                        }

                        // Pass empty string as value to search for, displaying all results
                        input.autocomplete( "search", "" );
                    });
            },

            _source: function( request, response ) {
                var matcher = new RegExp( $.ui.autocomplete.escapeRegex(request.term), "i" );
                response( this.element.children( "option" ).map(function() {
                    var text = $( this ).text();
                    if ( this.value && ( !request.term || matcher.test(text) ) )
                        return {
                            label: text,
                            value: text,
                            option: this
                        };
                }) );
            },

            _removeIfInvalid: function( event, ui ) {

                // Selected an item, nothing to do
                if ( ui.item ) {
                    return;
                }

                // Search for a match (case-insensitive)
                var value = this.input.val(),
                    valueLowerCase = value.toLowerCase(),
                    valid = false;
                this.element.children( "option" ).each(function() {
                    if ( $( this ).text().toLowerCase() === valueLowerCase ) {
                        this.selected = valid = true;
                        return false;
                    }
                });

                // Found a match, nothing to do
                if ( valid ) {
                    return;
                }

                // Remove invalid value
                this.input
                    .val( "" )
                    .attr( "title", value + " didn't match any item" )
                    .tooltip( "open" );
                this.element.val( "" );
                this._delay(function() {
                    this.input.tooltip( "close" ).attr( "title", "" );
                }, 2500 );
                this.input.autocomplete( "instance" ).term = "";
            },

            _destroy: function() {
                this.wrapper.remove();
                this.element.show();
            }
        });

        $( ".struct_combolookup" ).structcombolookup();
    };
}(jQuery));
