<?php

/**
 * @var BankDetail $model BankDetail model.
 * @var array $existedGroups Array of distinct groups from the CardOwnerType model.
 */

use common\component\generator\YiiFormHtmlHelper;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\widgets\ActiveForm;
use kartik\select2\Select2;
use common\models\CardOwnerType;
use yii\helpers\Url;

?>

<div class="jarviswidget" id="wid-id-1" data-widget-editbutton="false" data-widget-custombutton="false">
    <header>
        <span class="widget-icon"> <i class="fa fa-edit"></i> </span>
        <h2><?= $currentTitle ?? null ?></h2>
    </header>
    <div>
        <div class="widget-body no-padding">

            <?php if ($model->errors) { ?>
                <?php foreach ($model->errors as $errors) { ?>
                    <?php foreach ($errors as $error) { ?>
                        <div class="alert alert-danger">
                            <?= $error ?>
                        </div>
                    <?php } ?>
                <?php } ?>
            <?php } ?>

            <?php $bankDetailsForm = ActiveForm::begin([
                'method' => 'post',
                'id' => $this->context->id . '-edit-form',
                'enableAjaxValidation' => true,
                'enableClientValidation' => true,
                'validateOnSubmit' => true,
                'options' => [
                    'class' => 'smart-form',
                    'enctype' => 'multipart/form-data'
                ]
            ]);
            ?>
            <fieldset>
                <?php if (isset($ownerEntityId)) : ?>
                    <?= $bankDetailsForm->field($model, 'type_owner')->hiddenInput(['value' => $ownerEntityId, 'id' => 'typeOwnerId'])->label(false) ?>
                    <input type="hidden" id="bankDetailId" value="<?= $bankDetailId ?>">
                <?php endif; ?>

                <section class="col col-6">
                    <?= $bankDetailsForm->field($model, 'name')->textInput() ?>
                </section>

                <section class="col col-6">
                    <?= $bankDetailsForm->field($model, 'title')->textInput() ?>
                </section>

                <section class="col col-6">
                    <?= $bankDetailsForm->field($model, 'country_id', [])
                        ->dropDownList($countryList)
                        ->label(\Yii::t('dictionary', 'country')); ?>
                </section>

                <?php if ($model::findOne(['id' => $model->id])) { ?>
                    <section type="isMain" class="col col-2" style="display: none;">
                        <?= $bankDetailsForm->field($model, 'isMain', [])
                            ->dropDownList([$model->isMain => $model->isMain]) ?>
                    </section>
                    <input type="hidden" id="currentCountry" value="<?= $currentCountry ?>">
                <?php } ?>
            </fieldset>
            <?php
                ActiveForm::end();
            ?>
        </div>
        <div class="modal fade" id="successModal" tabindex="-1" role="dialog" aria-labelledby="successModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-body">
                        <h4 class="text-center font-weight-bold">
                            <?= \Yii::t('dictionary', 'data_saved_successfully') ?>
                        </h4>
                    </div>
                    <div class="modal-footer">
                        <a id="successModalClose" href="<?= Url::toRoute("/dispatcher/owner-bank-details/?id={$ownerEntityId}"); ?>" class="btn btn-primary" style="display: none">
                            <?= \Yii::t('dictionary', 'confirm') ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    window.onload = function() {
        const countrySelector = document.getElementById('bankdetails-country_id');
        if (countrySelector) {
            let currentCountry = document.getElementById('currentCountry');
            if(currentCountry) {
                renderDetailsByCountry(currentCountry.value);
            } else {
                // По дефолту рендерим RF по country_id = 1
                renderAdditionalBankDetails('additionaldetailsrf');
            }
            // const submitButton = document.querySelector('[type="submit"]');
            countrySelector.addEventListener('change', function(countryChange) {
                countryChange.preventDefault();
                renderDetailsByCountry(countrySelector.value);
            });
        } else {
            const submitButton = document.querySelector('[type="submit"]');
            submitButton.addEventListener('click', function(updateSubmit) 
            { 
                let requiredFields = document.querySelectorAll('[aria-required]');
                let allRequiredFieldsFilled = Array.from(requiredFields).every(field => field.value.trim() !== '');
                if (!allRequiredFieldsFilled) {
                    updateSubmit.preventDefault(); 
                    jarviswidgetAlerts(requiredFields);
                }
            });
        }
    }

    function renderDetailsByCountry(countryId) {
        if (countryId == 3) {
                renderAdditionalBankDetails('additionaldetailsuz');
            } else if (countryId == 4) {
                renderAdditionalBankDetails('additionaldetailstr');
            } else {
                renderAdditionalBankDetails('additionaldetailsrf');
        }
    }

    /**
     * Получение данных с основной и дополнительной информацией по реквизитам
     *
     * @param {string} countryId - ID страны, для прогрузки дополнительных деталей.
     * @return {json} bankDetailsData
     */
    function getBankDetailsParams(countryId) {
        const sections = document.querySelectorAll('section');
        const bankDetails = {};
        const additionalDetails = {};
        const typeOwnerId = document.getElementById('typeOwnerId');
        const bankDetailId = document.getElementById('bankDetailId');
        bankDetails[typeOwnerId.id] = typeOwnerId.value;
        additionalDetails[bankDetailId.id] = bankDetailId.value;
        sections.forEach((section) => {
            let inputOrSelect = section.querySelector('input, select');
            let sectionId = inputOrSelect.id;
            let value = inputOrSelect.value;
            if (sectionId.startsWith("bankdetails-")) {
                bankDetails[sectionId] = value;
            } else {
                additionalDetails[sectionId] = value;
            }
        });
        return {
            bankdetails: bankDetails,
            additionalDetails: additionalDetails
        };
    }

    const AdditionalDetailsobserver = new MutationObserver((mutationsList, observer) => {
        const submitButton = document.querySelector('[type="submit"]');
        if (submitButton) {
            AdditionalDetailsobserver.disconnect();
            submitButton.addEventListener('click', function(submitEvent) {
                let requiredFields = document.querySelectorAll('[aria-required]');
                submitEvent.preventDefault();
                let allRequiredFieldsFilled = Array.from(requiredFields).every(field => field.value.trim() !== '');
                if (allRequiredFieldsFilled) {
                    let bankDetailsParams = getBankDetailsParams();
                    saveBankDetailsModels(bankDetailsParams);
                } else {
                    jarviswidgetAlerts(requiredFields);
                }
            });
        }
    });

    function jarviswidgetAlerts(requiredFields) {
        const jQueryAlertText = $.validationEngineLanguage.allRules.required.alertText;
        for (let i = 0; i < requiredFields.length; i++) {
            if(requiredFields[i].value == '') {
                let inputDiv = requiredFields[i].parentElement;
                inputDiv.classList.add('has-error');
                let helpBlock = inputDiv.getElementsByClassName('help-block')[0];
                helpBlock.innerHTML = jQueryAlertText + ` "${inputDiv.querySelector('label').innerText}"`;
            }
        }
    }

    function saveBankDetailsModels(bankDetailsParams) {
        let formUrl = 'ajaxmodelsdatasave';
        if(document.querySelector('[type="isMain"]')) {
            formUrl = '../' + formUrl;
        };
        $.ajax({
            url: formUrl,
            type: "POST",
            data: {
                bankDetailsParams: bankDetailsParams
            },
            success: function(response) {
                if (response.status == 200) {
                    const modal = document.getElementById('successModal');
                    const successModalClose = document.getElementById('successModalClose');
                    $(modal).modal('show');
                    setTimeout(() => {
                        successModalClose.click();
                    }, 500);
                }
            },
            error: function(xhr, status, error) {
                console.error(error);
            }
        });
    }

    /**
     * Рендер дополнительных деталей банка при первом создании реквизитов
     *
     * @param {string} formUrl - URL формы дополнительных деталей банка.
     * @return {void}
     */
    function renderAdditionalBankDetails(formUrl) {
        let bankDetailId = document.getElementById('bankDetailId');
        if(document.querySelector('[type="isMain"]')) {
            formUrl = '../' + formUrl;
        };
        $.ajax({
            url: formUrl,
            type: "POST",
            data: {
                bankDetailId: bankDetailId.value
            },
            success: function(response) {
                const additionalFieldset = $(response).find('#additionalBankDetails').html();
                let existedAdditionalDetails = document.querySelectorAll('fieldset')[1];
                const jarviswidget = document.getElementsByClassName('jarviswidget')[0];
                AdditionalDetailsobserver.observe(jarviswidget, {
                    childList: true,
                    subtree: true
                });
                if (existedAdditionalDetails) {
                    const submitButtonDuplicate = document.querySelectorAll('footer');
                    existedAdditionalDetails.remove();
                    submitButtonDuplicate[0].remove();
                }
                bankDetailsField = document.querySelectorAll('fieldset')[0];
                bankDetailsField.insertAdjacentHTML('afterend', additionalFieldset);
            },
            error: function(xhr, status, error) {
                console.error(error);
            }
        });
    }

    function getSelector(type) {
        let selectorDiv = document.querySelector(`[type=${type}]`);
        if (selectorDiv) {
            let selector = selectorDiv.querySelector('select');
            return selector
        }
    }
</script>