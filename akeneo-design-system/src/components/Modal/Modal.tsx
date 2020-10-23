import React, {ReactElement, Ref} from 'react';
import styled from 'styled-components';
import {getColor, getFontSize} from '../../theme';
import {CloseIcon} from '../../icons';
import {IllustrationProps} from '../../illustrations/IllustrationProps';
import {useShortcut} from '../../hooks';
import {Key} from '../../shared';

const ModalContainer = styled.div`
  position: fixed;
  width: 100vw;
  height: 100vh;
  top: 0;
  left: 0;
  background: ${getColor('white')};
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  z-index: 2000;
  overflow: hidden;
  cursor: default;
`;

const ModalCloseButton = styled.button`
  background: none;
  border: none;
  margin: 0;
  padding: 0;
  position: absolute;
  top: 40px;
  left: 40px;
  cursor: pointer;
  color: ${getColor('grey', 100)};
`;

const ModalContent = styled.div`
  display: flex;
  position: relative;
`;

const Separator = styled.div`
  width: 1px;
  height: 100%;
  background-color: ${getColor('brand', 100)};
  margin: 0 40px;
`;

const ModalChildren = styled.div`
  display: flex;
  flex-direction: column;
  padding: 20px 0;
`;

//TODO add DSM component
const Surtitle = styled.div`
  height: 20px;
  color: ${getColor('brand', 120)};
  font-size: ${getFontSize('default')};
  text-transform: uppercase;
`;

//TODO add DSM component
const Title = styled.div`
  display: flex;
  align-items: center;
  height: 40px;
  color: ${getColor('grey', 140)};
  font-size: ${getFontSize('title')};
  margin-bottom: 10px;
`;

//TODO add DSM component
const ButtonsContainer = styled.div`
  display: flex;
  gap: 10px;
  margin: 20px 0;
`;

type ModalProps = {
  /**
   * Prop to display or hide the Modal.
   */
  isOpen: boolean;

  /**
   * The handler to call when the Modal is closed.
   */
  onClose: () => void;

  /**
   * Illustration to display.
   */
  illustration?: ReactElement<IllustrationProps>;

  /**
   * The content of the modal.
   */
  children?: string;
};

/**
 * Modal component.
 */
const Modal = React.forwardRef<HTMLDivElement, ModalProps>(
  ({isOpen, onClose, illustration, children, ...rest}: ModalProps, forwardedRef: Ref<HTMLDivElement>) => {
    useShortcut(Key.Escape, onClose, forwardedRef);

    if (!isOpen) return null;

    return (
      <ModalContainer ref={forwardedRef} {...rest}>
        <ModalCloseButton onClick={onClose}>
          <CloseIcon size={20} />
        </ModalCloseButton>
        <ModalContent>
          {undefined !== illustration && (
            <>
              {React.cloneElement(illustration, {size: 220})}
              <Separator />
            </>
          )}
          <ModalChildren>{children}</ModalChildren>
        </ModalContent>
      </ModalContainer>
    );
  }
);

export {Modal};
export {Surtitle, Title, ButtonsContainer};
