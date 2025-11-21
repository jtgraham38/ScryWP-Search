/**
 * ScryWP Search Weights Input JavaScript
 */

jQuery(document).ready(function($) {
    'use strict';
    
    // Add new factor
    $('#scrywp-add-factor-btn').on('click', function() {
        const factorKey = $('#scrywp-factor-select').val();
        if (!factorKey) {
            alert('Please select a factor to add.');
            return;
        }
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: scry_ms_weights_ajax.add_factor_action,
                factor_name: factorKey,
                nonce: scry_ms_weights_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    location.reload(); // Reload to show new factor
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function() {
                alert('An error occurred while adding the factor.');
            }
        });
    });
    
    // Remove factor
    $(document).on('click', '.scrywp-remove-factor', function() {
        const factorKey = $(this).data('factor');
        const factorItem = $(this).closest('.scrywp-factor-item');
        
        // Prevent removal of semantic similarity factor
        if (factorKey === 'semantic_similarity') {
            alert('Semantic similarity factor cannot be removed');
            return;
        }
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: scry_ms_weights_ajax.remove_factor_action,
                factor_name: factorKey,
                nonce: scry_ms_weights_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    factorItem.fadeOut(300, function() {
                        $(this).remove();
                        updateTotalWeight();
                    });
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function() {
                alert('An error occurred while removing the factor.');
            }
        });
    });
    
    // Update weight on slider change
    $(document).on('input', '.scrywp-weight-slider', function() {
        const weight = parseFloat($(this).val());
        const factorKey = $(this).data('factor');
        const weightValue = $(this).closest('.scrywp-weight-control').find('.scrywp-weight-value');
        
        weightValue.text(weight.toFixed(3));
        updateTotalWeight();
    });
    
    // Save weights
    $('#scrywp-save-weights').on('click', function() {
        const weights = {};
        $('.scrywp-weight-slider').each(function() {
            const factorKey = $(this).data('factor');
            const weight = parseFloat($(this).val());
            weights[factorKey] = weight;
        });
        
        const saveBtn = $(this);
        const saveStatus = $('#scrywp-save-status');
        
        saveBtn.prop('disabled', true);
        saveStatus.text('Saving...').removeClass('success error');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: scry_ms_weights_ajax.update_weights_action,
                weights: weights,
                nonce: scry_ms_weights_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    saveStatus.text('Settings saved successfully!').addClass('success');
                    // Update weights with returned values (no normalization)
                    if (response.data.weights) {
                        Object.keys(response.data.weights).forEach(function(factorKey) {
                            const slider = $('.scrywp-weight-slider[data-factor="' + factorKey + '"]');
                            const weightValue = slider.closest('.scrywp-weight-control').find('.scrywp-weight-value');
                            slider.val(response.data.weights[factorKey]);
                            weightValue.text(response.data.weights[factorKey].toFixed(3));
                        });
                        updateTotalWeight();
                    }
                } else {
                    saveStatus.text('Error: ' + response.data).addClass('error');
                }
            },
            error: function() {
                saveStatus.text('An error occurred while saving.').addClass('error');
            },
            complete: function() {
                saveBtn.prop('disabled', false);
                setTimeout(function() {
                    saveStatus.text('').removeClass('success error');
                }, 3000);
            }
        });
    });
    
    // Update total weight display
    function updateTotalWeight() {
        let total = 0;
        $('.scrywp-weight-slider').each(function() {
            total += parseFloat($(this).val());
        });
        $('#scrywp-total-weight').text(total.toFixed(3));
    }
    
    // Initialize total weight
    updateTotalWeight();
});
