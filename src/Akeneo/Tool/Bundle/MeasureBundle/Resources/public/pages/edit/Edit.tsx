import React, {useCallback, useContext, useEffect, useState} from 'react';
import {useHistory, useParams} from 'react-router-dom';
import styled from 'styled-components';
import {useMeasurementFamily} from 'akeneomeasure/hooks/use-measurement-family';
import {TranslateContext} from 'akeneomeasure/context/translate-context';
import {UnitTab} from 'akeneomeasure/pages/edit/UnitTab';
import {PropertyTab} from 'akeneomeasure/pages/edit/PropertyTab';
import {PageHeader, PageHeaderPlaceholder} from 'akeneomeasure/shared/components/PageHeader';
import {PimView} from 'akeneomeasure/bridge/legacy/pim-view/PimView';
import {Breadcrumb} from 'akeneomeasure/shared/components/Breadcrumb';
import {BreadcrumbItem} from 'akeneomeasure/shared/components/BreadcrumbItem';
import {Button} from 'akeneomeasure/shared/components/Button';
import {addUnit, getMeasurementFamilyLabel, MeasurementFamily} from 'akeneomeasure/model/measurement-family';
import {Unit, UnitCode} from 'akeneomeasure/model/unit';
import {UserContext} from 'akeneomeasure/context/user-context';
import {PageContent} from 'akeneomeasure/shared/components/PageContent';
import {
  DropdownLink,
  SecondaryActionsDropdownButton,
} from 'akeneomeasure/shared/components/SecondaryActionsDropdownButton';
import {NotificationLevel, NotifyContext} from 'akeneomeasure/context/notify-context';
import {filterErrors, ValidationError} from 'akeneomeasure/model/validation-error';
import {useSaveMeasurementFamilySaver} from 'akeneomeasure/pages/edit/hooks/use-save-measurement-family-saver';
import {ErrorBadge} from 'akeneomeasure/shared/components/ErrorBadge';
import {useToggleState} from 'akeneomeasure/shared/hooks/use-toggle-state';
import {CreateUnit} from 'akeneomeasure/pages/create-unit/CreateUnit';
import {SubsectionHelper, HELPER_LEVEL_WARNING} from 'akeneomeasure/shared/components/SubsectionHelper';
import {useUnsavedChanges} from 'akeneomeasure/shared/hooks/use-unsaved-changes';
import {UnsavedChanges} from 'akeneomeasure/shared/components/UnsavedChanges';
import {UnsavedChangesContext} from 'akeneomeasure/context/unsaved-changes-context';

enum Tab {
  Units = 'units',
  Properties = 'properties',
}

const Container = styled.div`
  /* 70 = TabContainer height + margin */
  height: calc(100% - 70px);
  display: flex;
`;

const TabsContainer = styled.div`
  margin-bottom: 20px;
`;

const Tabs = styled.div`
  display: flex;
  width: 100%;
  height: 50px;
  border-bottom: 1px solid ${props => props.theme.color.grey80};
`;

const TabSelector = styled.div<{isActive: boolean}>`
  width: 90px;
  padding: 13px 0;
  cursor: pointer;
  font-size: ${props => props.theme.fontSize.big};
  color: ${props => (props.isActive ? props.theme.color.purple100 : 'inherit')};
  border-bottom: 3px solid ${props => (props.isActive ? props.theme.color.purple100 : 'transparent')};
  display: flex;
  align-items: baseline;

  > :last-child {
    margin-left: 5px;
  }
`;

const hasTabErrors = (tab: Tab, errors: ValidationError[]): boolean => {
  const unitsErrorCount = filterErrors(errors, 'units').length;

  switch (tab) {
    case Tab.Units:
      return 0 < unitsErrorCount;
    case Tab.Properties:
      return 0 < errors.length - unitsErrorCount;
    default:
      return false;
  }
};

const Edit = () => {
  const __ = useContext(TranslateContext);
  const history = useHistory();
  const locale = useContext(UserContext)('uiLocale');
  const {measurementFamilyCode} = useParams() as {measurementFamilyCode: string};
  const [currentTab, setCurrentTab] = useState<Tab>(Tab.Units);
  const [measurementFamily, setMeasurementFamily] = useMeasurementFamily(measurementFamilyCode);
  const [selectedUnitCode, selectUnitCode] = useState<UnitCode|null>(null);
  const [errors, setErrors] = useState<ValidationError[]>([]);
  const saveMeasurementFamily = useSaveMeasurementFamilySaver();
  const notify = useContext(NotifyContext);
  const [isAddUnitModalOpen, openAddUnitModal, closeAddUnitModal] = useToggleState(false);

  const {setHasUnsavedChanges} = useContext(UnsavedChangesContext);
  const [isModified, resetState] = useUnsavedChanges<MeasurementFamily | null>(
    measurementFamily,
    __('pim_ui.flash.unsaved_changes')
  );
  useEffect(() => setHasUnsavedChanges(isModified), [isModified]);

  // If the measurement family code changes, we select the standard unit code by default
  useEffect(() => {
    if (null === measurementFamily) {
      return;
    }

    selectUnitCode(measurementFamily.standard_unit_code);
  }, [measurementFamily?.code]);

  const handleSave = useCallback(async () => {
    if (null === measurementFamily) {
      return;
    }

    setErrors([]);

    try {
      const response = await saveMeasurementFamily(measurementFamily);

      switch (response.success) {
        case true:
          resetState();
          notify(NotificationLevel.SUCCESS, __('measurements.family.save.flash.success'));
          break;

        case false:
          setErrors(response.errors);
          break;
      }
    } catch (error) {
      console.error(error);
      notify(NotificationLevel.ERROR, __('measurements.family.save.flash.error'));
    }
  }, [measurementFamily, locale, saveMeasurementFamily, notify, __, setErrors]);

  const handleNewUnit = useCallback((unit: Unit) => {
    if (null === measurementFamily) {
      return;
    }

    setMeasurementFamily(addUnit(measurementFamily, unit));
    selectUnitCode(unit.code);
  }, [
    setMeasurementFamily,
    measurementFamily,
    selectUnitCode,
  ]);

  if (undefined === measurementFamilyCode || null === measurementFamily) {
    return null;
  }

  return (
    <>
      {isAddUnitModalOpen && (
        <CreateUnit measurementFamily={measurementFamily} onClose={closeAddUnitModal} onNewUnit={handleNewUnit} />
      )}

      <PageHeader
        userButtons={
          <PimView
            className="AknTitleContainer-userMenuContainer AknTitleContainer-userMenu"
            viewName="pim-measurements-user-navigation"
          />
        }
        buttons={[
          <SecondaryActionsDropdownButton title={__('pim_common.other_actions')} key={0}>
            <DropdownLink
              onClick={() => {
                //TODO delete measurement family
              }}
            >
              {__('measurements.family.delete')}
            </DropdownLink>
          </SecondaryActionsDropdownButton>,
          <Button color="blue" outline={true} onClick={openAddUnitModal}>
            {__('measurements.unit.add')}
          </Button>,
          <Button onClick={handleSave}>{__('pim_common.save')}</Button>,
        ]}
        breadcrumb={
          <Breadcrumb>
            <BreadcrumbItem>{__('pim_menu.tab.settings')}</BreadcrumbItem>
            <BreadcrumbItem onClick={() => history.push('/')}>{__('pim_menu.item.measurements')}</BreadcrumbItem>
          </Breadcrumb>
        }
        state={isModified && <UnsavedChanges />}
      >
        {null === measurementFamily ? (
          <div className={`AknLoadingPlaceHolderContainer`}>
            <PageHeaderPlaceholder />
          </div>
        ) : (
          <div>{getMeasurementFamilyLabel(measurementFamily, locale)}</div>
        )}
      </PageHeader>

      <PageContent>
        <TabsContainer>
          <Tabs>
            {Object.values(Tab).map((tab: Tab) => (
              <TabSelector key={tab} onClick={() => setCurrentTab(tab)} isActive={currentTab === tab}>
                {__(`measurements.family.tab.${tab}`)}
                {hasTabErrors(tab, errors) && <ErrorBadge />}
              </TabSelector>
            ))}
          </Tabs>
          {measurementFamily.is_locked && (
            <SubsectionHelper level={HELPER_LEVEL_WARNING}>{__('measurements.family.is_locked')}</SubsectionHelper>
          )}
        </TabsContainer>
        <Container>
          {currentTab === Tab.Units && null !== selectedUnitCode && (
            <UnitTab
              measurementFamily={measurementFamily}
              onMeasurementFamilyChange={setMeasurementFamily}
              errors={filterErrors(errors, 'units')}
              selectedUnitCode={selectedUnitCode}
              selectUnitCode={selectUnitCode}
            />
          )}
          {currentTab === Tab.Properties && (
            <PropertyTab
              measurementFamily={measurementFamily}
              onMeasurementFamilyChange={setMeasurementFamily}
              errors={errors}
            />
          )}
        </Container>
      </PageContent>
    </>
  );
};

export {Edit};
